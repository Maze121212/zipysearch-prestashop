<?php
/**
 * ZipySearch - Intelligent Search Engine
 *
 * @author    ZipySearch <contact@zipysearch.com>
 * @copyright ZipySearch
 * @license   Commercial license
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class ZipySearchExporter
{
    private $context;
    private $idLang;
    private $idShop;

    public function __construct($context = null)
    {
        $this->context = $context;
        $this->idLang = $this->context->language->id;
        $this->idShop = $this->context->shop->id;
    }

    public function export()
    {
        echo $this->formatLine([
            'title', 'link', 'description', 'id', 'price',
            'image link', 'product type', 'brand', 'color',
            'sale_price', 'qt_vendu', 'id_model', 'stocks',
        ]);

        $offset = 0;
        $batchSize = 500;

        do {
            $products = $this->getBatch($offset, $batchSize);

            foreach ($products as $row) {
                $combinations = $this->getCombinations($row['id_product']);

                if (!empty($combinations)) {
                    foreach ($combinations as $combination) {
                        echo $this->formatLine($this->transformCombination($row, $combination));
                        flush();
                    }
                } else {
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
        $sql->leftJoin('product_lang', 'pl', 'p.id_product = pl.id_product AND pl.id_lang = ' . (int) $this->idLang . ' AND pl.id_shop = ' . (int) $this->idShop);
        $sql->leftJoin('product_shop', 'ps', 'p.id_product = ps.id_product AND ps.id_shop = ' . (int) $this->idShop);
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
        $sql->leftJoin('product_attribute_shop', 'pas', 'pa.id_product_attribute = pas.id_product_attribute AND pas.id_shop = ' . (int) $this->idShop);
        $sql->where('pa.id_product = ' . (int) $idProduct);
        $sql->orderBy('pa.id_product_attribute ASC');

        return Db::getInstance()->executeS($sql);
    }

    private function transform($row)
    {
        $price = Product::getPriceStatic($row['id_product'], true, null, 2);
        $priceNoReduction = Product::getPriceStatic($row['id_product'], true, null, 2, null, false, false);
        $salePrice = ($price < $priceNoReduction) ? $price : null;
        $regularPrice = $salePrice !== null ? $priceNoReduction : $price;

        $imageUrl = $this->getProductImage($row['id_product'], null, $row['link_rewrite']);
        $productUrl = $this->context->link->getProductLink($row['id_product']);
        $description = $this->cleanDescription($row['description_short']);
        $categoryPath = $this->getCategoryPath($row['id_category_default']);
        $color = $this->getProductColor($row['id_product'], null);
        $sales = $this->getSales($row['id_product']);
        $stock = StockAvailable::getQuantityAvailableByProduct($row['id_product']);

        return [
            $row['name'] ?? '',
            $productUrl,
            $description,
            $row['id_product'],
            number_format($regularPrice, 2, '.', ''),
            $imageUrl,
            $categoryPath,
            $row['manufacturer_name'] ?? '',
            $color,
            $salePrice ? number_format($salePrice, 2, '.', '') : '',
            $sales,
            $row['id_product'],
            max(0, $stock),
        ];
    }

    private function transformCombination($row, $combination)
    {
        $idProduct = $row['id_product'];
        $idProductAttribute = $combination['id_product_attribute'];

        $price = Product::getPriceStatic($idProduct, true, $idProductAttribute, 2);
        $priceNoReduction = Product::getPriceStatic($idProduct, true, $idProductAttribute, 2, null, false, false);
        $salePrice = ($price < $priceNoReduction) ? $price : null;
        $regularPrice = $salePrice !== null ? $priceNoReduction : $price;

        $imageUrl = $this->getProductImage($idProduct, $idProductAttribute, $row['link_rewrite']);

        $productUrl = $this->context->link->getProductLink(
            $idProduct,
            $row['link_rewrite'],
            null,
            null,
            $this->idLang,
            $this->idShop,
            $idProductAttribute
        );

        $description = $this->cleanDescription($row['description_short']);
        $categoryPath = $this->getCategoryPath($row['id_category_default']);
        $attributes = $this->getCombinationAttributes($idProductAttribute);
        $color = $attributes['color'] ?? '';
        $attributeNames = $attributes['names'] ?? [];
        $productName = $row['name'] ?? '';

        if (!empty($attributeNames)) {
            $productName .= ' - ' . implode(' / ', $attributeNames);
        }

        $sales = $this->getSales($idProduct);
        $stock = StockAvailable::getQuantityAvailableByProduct($idProduct, $idProductAttribute);

        return [
            $productName,
            $productUrl,
            $description,
            $idProduct,
            number_format($regularPrice, 2, '.', ''),
            $imageUrl,
            $categoryPath,
            $row['manufacturer_name'] ?? '',
            $color,
            $salePrice ? number_format($salePrice, 2, '.', '') : '',
            $sales,
            $idProduct . '-' . $idProductAttribute,
            max(0, $stock),
        ];
    }

    private function getCombinationAttributes($idProductAttribute)
    {
        $sql = new DbQuery();
        $sql->select('al.name as attribute_name, agl.name as group_name');
        $sql->from('product_attribute_combination', 'pac');
        $sql->leftJoin('attribute', 'a', 'pac.id_attribute = a.id_attribute');
        $sql->leftJoin('attribute_lang', 'al', 'a.id_attribute = al.id_attribute AND al.id_lang = ' . (int) $this->idLang);
        $sql->leftJoin('attribute_group_lang', 'agl', 'a.id_attribute_group = agl.id_attribute_group AND agl.id_lang = ' . (int) $this->idLang);
        $sql->where('pac.id_product_attribute = ' . (int) $idProductAttribute);

        $results = Db::getInstance()->executeS($sql);

        $attributes = [
            'names' => [],
            'color' => '',
        ];

        if ($results) {
            foreach ($results as $attr) {
                $attributes['names'][] = $attr['attribute_name'];
                $groupLower = strtolower($attr['group_name']);

                if (in_array($groupLower, ['couleur', 'color', 'colour'])) {
                    $attributes['color'] = $attr['attribute_name'];
                }
            }
        }

        return $attributes;
    }

    private function getProductImage($idProduct, $idProductAttribute, $linkRewrite)
    {
        $imageUrl = '';

        if ($idProductAttribute) {
            $images = Product::_getAttributeImageAssociations($idProductAttribute);

            if (!empty($images)) {
                $idImage = reset($images);
                $imageUrl = $this->context->link->getImageLink($linkRewrite, $idImage, 'home_default');

                return $imageUrl;
            }
        }

        $cover = Product::getCover($idProduct);

        if ($cover) {
            $imageUrl = $this->context->link->getImageLink($linkRewrite, $cover['id_image'], 'home_default');
        }

        return $imageUrl;
    }

    private function getProductColor($idProduct, $idProductAttribute)
    {
        $sql = new DbQuery();
        $sql->select('al.name');
        $sql->from('product_attribute_combination', 'pac');
        $sql->leftJoin('attribute', 'a', 'pac.id_attribute = a.id_attribute');
        $sql->leftJoin('attribute_lang', 'al', 'a.id_attribute = al.id_attribute AND al.id_lang = ' . (int) $this->idLang);
        $sql->leftJoin('attribute_group_lang', 'agl', 'a.id_attribute_group = agl.id_attribute_group AND agl.id_lang = ' . (int) $this->idLang);
        $sql->leftJoin('product_attribute', 'pa', 'pac.id_product_attribute = pa.id_product_attribute');
        $sql->where('pa.id_product = ' . (int) $idProduct);
        $sql->where('LOWER(agl.name) IN ("couleur", "color", "colour")');

        if ($idProductAttribute) {
            $sql->where('pa.id_product_attribute = ' . (int) $idProductAttribute);
        }

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
        $sql = 'SELECT SUM(od.product_quantity) FROM ' . _DB_PREFIX_ . 'order_detail od WHERE od.product_id = ' . (int) $idProduct;

        return (int) Db::getInstance()->getValue($sql);
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
