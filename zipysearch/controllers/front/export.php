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

class ZipySearchExportModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        // Verifier le token
        $token = Tools::getValue('token');
        $validToken = Configuration::get('ZIPYSEARCH_EXPORT_TOKEN');

        if (!$token || $token !== $validToken) {
            header('HTTP/1.1 403 Forbidden');
            exit('Invalid token');
        }

        // Headers CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="products.csv"');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        // Export
        require_once _PS_MODULE_DIR_ . 'zipysearch/classes/ZipySearchExporter.php';
        $exporter = new ZipySearchExporter($this->context);
        $exporter->export();
        exit;
    }
}
