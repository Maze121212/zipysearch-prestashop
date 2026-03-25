<?php
function upgrade_module_1_0_5($module)
{
    // Ajout des champs reference et barcode (ean13/upc) dans l'export CSV
    return true;
}
