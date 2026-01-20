<?php
/**
 * ZipySearch - Moteur de recherche intelligent
 *
 * @author    ZipySearch <contact@zipysearch.com>
 * @copyright ZipySearch
 * @license   Commercial license
 */

class ZipySearchExporter
{
    private $context;
    private $idLang;
    private $idShop;

    public function __construct()
    {
        $this->context = Context::getContext();
        $this->idLang = $this->context->language->id;
        $this->idShop = $this->context->shop->id;
    }

    public function export()
    {
        // Header
        echo $this->formatLine([
            'title', 'link', 'description', 'id', 'price',
            'image link', 'product type', 'brand', 'color',
            'sale_price', 'qt_vendu', 'id_model', 'stocks'
        ]);

        $offset = 0;
        $batchSize = 500;

        do {
            $products = $this->getBatch($offset, $batchSize);
            foreach ($products as $row) {
                $combinations = $this->getCombinations($row['id_product']);

                if (!empty($combinations)) {
                    // Produit avec déclinaisons : une ligne par variante
                    foreach ($combinations as $combination) {
                        echo $this->formatLine($this->transformCombination($row, $combination));
                        flush();
                    }
                } else {
                    // Produit simple sans déclinaison
                    echo $this->formatLine($this->transform($row));
                    flush();
                }
            }
            $offset += $batchSize;
        } while (count($products) === $batchSize);
    }

    private function getBatch($offset, $limit)
    {
        $sql = new DbQuery();
        $sql->select('p.id_product, pl.name, pl.description_short, pl.link_rewrite');
        $sql->select('p.id_category_default, m.name as manufacturer_name');
        $sql->from('product', 'p');
        $sql->leftJoin('product_lang', 'pl', 'p.id_product = pl.id_product AND pl.id_lang = ' . (int)$this->idLang . ' AND pl.id_shop = ' . (int)$this->idShop);
        $sql->leftJoin('product_shop', 'ps', 'p.id_product = ps.id_product AND ps.id_shop = ' . (int)$this->idShop);
        $sql->leftJoin('manufacturer', 'm', 'p.id_manufacturer = m.id_manufacturer');
        $sql->where('p.active = 1 AND ps.active = 1');
        $sql->orderBy('p.id_product ASC');
        $sql->limit($limit, $offset);

        return Db::getInstance()->executeS($sql);
    }

    private function getCombinations($idProduct)
    {
        $sql = new DbQuery();
        $sql->select('pa.id_product_attribute, pa.reference, pa.price as price_impact');
        $sql->from('product_attribute', 'pa');
        $sql->leftJoin('product_attribute_shop', 'pas', 'pa.id_product_attribute = pas.id_product_attribute AND pas.id_shop = ' . (int)$this->idShop);
        $sql->where('pa.id_product = ' . (int)$idProduct);
        $sql->orderBy('pa.id_product_attribute ASC');

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Transforme un produit simple (sans déclinaison)
     */
    private function transform($row)
    {
        // Prix
        $price = Product::getPriceStatic($row['id_product'], true, null, 2);
        $priceNoReduction = Product::getPriceStatic($row['id_product'], true, null, 2, null, false, false);
        $salePrice = ($price < $priceNoReduction) ? $price : null;
        $regularPrice = $salePrice !== null ? $priceNoReduction : $price;

        // Image
        $imageUrl = $this->getProductImage($row['id_product'], null, $row['link_rewrite']);

        // URL
        $productUrl = $this->context->link->getProductLink($row['id_product']);

        // Description sans HTML
        $description = $this->cleanDescription($row['description_short']);

        // Categories
        $categoryPath = $this->getCategoryPath($row['id_category_default']);

        // Couleur (pour produit simple, prend la première si existe)
        $color = $this->getProductColor($row['id_product'], null);

        // Ventes
        $sales = $this->getSales($row['id_product']);

        // Stock
        $stock = StockAvailable::getQuantityAvailableByProduct($row['id_product']);

        return [
            $row['name'] ?? '',                              // title
            $productUrl,                                      // link
            $description,                                     // description
            $row['id_product'],                               // id (produit parent)
            number_format($regularPrice, 2, '.', ''),         // price
            $imageUrl,                                        // image link
            $categoryPath,                                    // product type
            $row['manufacturer_name'] ?? '',                  // brand
            $color,                                           // color
            $salePrice ? number_format($salePrice, 2, '.', '') : '', // sale_price
            $sales,                                           // qt_vendu
            $row['id_product'],                               // id_model (unique, = id pour produit simple)
            max(0, $stock),                                   // stocks
        ];
    }

    /**
     * Transforme une déclinaison de produit
     */
    private function transformCombination($row, $combination)
    {
        $idProduct = $row['id_product'];
        $idProductAttribute = $combination['id_product_attribute'];

        // Prix de la déclinaison
        $price = Product::getPriceStatic($idProduct, true, $idProductAttribute, 2);
        $priceNoReduction = Product::getPriceStatic($idProduct, true, $idProductAttribute, 2, null, false, false);
        $salePrice = ($price < $priceNoReduction) ? $price : null;
        $regularPrice = $salePrice !== null ? $priceNoReduction : $price;

        // Image de la déclinaison (ou image principale si pas d'image spécifique)
        $imageUrl = $this->getProductImage($idProduct, $idProductAttribute, $row['link_rewrite']);

        // URL avec déclinaison
        $productUrl = $this->context->link->getProductLink(
            $idProduct,
            $row['link_rewrite'],
            null,
            null,
            $this->idLang,
            $this->idShop,
            $idProductAttribute
        );

        // Description sans HTML
        $description = $this->cleanDescription($row['description_short']);

        // Categories
        $categoryPath = $this->getCategoryPath($row['id_category_default']);

        // Attributs de la déclinaison (couleur, taille, etc.)
        $attributes = $this->getCombinationAttributes($idProductAttribute);
        $color = $attributes['color'] ?? '';

        // Nom du produit avec attributs
        $attributeNames = $attributes['names'] ?? [];
        $productName = $row['name'] ?? '';
        if (!empty($attributeNames)) {
            $productName .= ' - ' . implode(' / ', $attributeNames);
        }

        // Ventes (global au produit)
        $sales = $this->getSales($idProduct);

        // Stock de la déclinaison
        $stock = StockAvailable::getQuantityAvailableByProduct($idProduct, $idProductAttribute);

        return [
            $productName,                                     // title (avec attributs)
            $productUrl,                                      // link
            $description,                                     // description
            $idProduct,                                       // id (produit parent pour regroupement)
            number_format($regularPrice, 2, '.', ''),         // price
            $imageUrl,                                        // image link
            $categoryPath,                                    // product type
            $row['manufacturer_name'] ?? '',                  // brand
            $color,                                           // color
            $salePrice ? number_format($salePrice, 2, '.', '') : '', // sale_price
            $sales,                                           // qt_vendu
            $idProduct . '-' . $idProductAttribute,           // id_model (identifiant unique)
            max(0, $stock),                                   // stocks
        ];
    }

    /**
     * Récupère les attributs d'une déclinaison
     */
    private function getCombinationAttributes($idProductAttribute)
    {
        $sql = new DbQuery();
        $sql->select('al.name as attribute_name, agl.name as group_name');
        $sql->from('product_attribute_combination', 'pac');
        $sql->leftJoin('attribute', 'a', 'pac.id_attribute = a.id_attribute');
        $sql->leftJoin('attribute_lang', 'al', 'a.id_attribute = al.id_attribute AND al.id_lang = ' . (int)$this->idLang);
        $sql->leftJoin('attribute_group_lang', 'agl', 'a.id_attribute_group = agl.id_attribute_group AND agl.id_lang = ' . (int)$this->idLang);
        $sql->where('pac.id_product_attribute = ' . (int)$idProductAttribute);

        $results = Db::getInstance()->executeS($sql);

        $attributes = [
            'names' => [],
            'color' => '',
        ];

        if ($results) {
            foreach ($results as $attr) {
                $attributes['names'][] = $attr['attribute_name'];

                // Détecter la couleur
                $groupLower = strtolower($attr['group_name']);
                if (in_array($groupLower, ['couleur', 'color', 'colour'])) {
                    $attributes['color'] = $attr['attribute_name'];
                }
            }
        }

        return $attributes;
    }

    /**
     * Récupère l'image d'un produit ou d'une déclinaison
     */
    private function getProductImage($idProduct, $idProductAttribute, $linkRewrite)
    {
        $imageUrl = '';

        // Essayer de récupérer l'image de la déclinaison
        if ($idProductAttribute) {
            $images = Product::_getAttributeImageAssociations($idProductAttribute);
            if (!empty($images)) {
                $idImage = reset($images);
                $imageUrl = $this->context->link->getImageLink($linkRewrite, $idImage, 'home_default');
                return $imageUrl;
            }
        }

        // Image principale du produit
        $cover = Product::getCover($idProduct);
        if ($cover) {
            $imageUrl = $this->context->link->getImageLink($linkRewrite, $cover['id_image'], 'home_default');
        }

        return $imageUrl;
    }

    /**
     * Récupère la couleur d'un produit simple
     */
    private function getProductColor($idProduct, $idProductAttribute)
    {
        $sql = new DbQuery();
        $sql->select('al.name');
        $sql->from('product_attribute_combination', 'pac');
        $sql->leftJoin('attribute', 'a', 'pac.id_attribute = a.id_attribute');
        $sql->leftJoin('attribute_lang', 'al', 'a.id_attribute = al.id_attribute AND al.id_lang = ' . (int)$this->idLang);
        $sql->leftJoin('attribute_group_lang', 'agl', 'a.id_attribute_group = agl.id_attribute_group AND agl.id_lang = ' . (int)$this->idLang);
        $sql->leftJoin('product_attribute', 'pa', 'pac.id_product_attribute = pa.id_product_attribute');
        $sql->where('pa.id_product = ' . (int)$idProduct);
        $sql->where('LOWER(agl.name) IN ("couleur", "color", "colour")');

        if ($idProductAttribute) {
            $sql->where('pa.id_product_attribute = ' . (int)$idProductAttribute);
        }

        // Pas de limit() ici, getValue() ajoute automatiquement LIMIT 1
        return Db::getInstance()->getValue($sql) ?: '';
    }

    private function getCategoryPath($idCategory)
    {
        $categories = [];
        $cat = new Category($idCategory, $this->idLang);
        $rootId = Category::getRootCategory()->id;

        while ($cat->id && $cat->id != $rootId) {
            array_unshift($categories, $cat->name);
            $cat = new Category($cat->id_parent, $this->idLang);
        }

        return implode(' > ', $categories);
    }

    private function getSales($idProduct)
    {
        $sql = 'SELECT SUM(od.product_quantity) FROM ' . _DB_PREFIX_ . 'order_detail od WHERE od.product_id = ' . (int)$idProduct;
        return (int)Db::getInstance()->getValue($sql);
    }

    private function cleanDescription($description)
    {
        $description = strip_tags(html_entity_decode($description ?? ''));
        return preg_replace('/\s+/', ' ', trim($description));
    }

    private function formatLine($fields)
    {
        $output = [];
        foreach ($fields as $field) {
            $field = str_replace('"', '""', $field);
            $output[] = '"' . $field . '"';
        }
        return implode(';', $output) . "\n";
    }
}
