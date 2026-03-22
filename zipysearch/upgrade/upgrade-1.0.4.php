<?php
function upgrade_module_1_0_4($module)
{
    return $module->registerHook('displayBeforeBodyClosingTag')
        && $module->registerHook('actionFrontControllerSetMedia');
}
