<?php
// Sample Plugin Main Logic
use Services\PluginEngine;

// Hook into Management Menu
PluginEngine::add_action("management_menu_items", function() {
    echo '<a href="#" onclick="alert(\'\ud83c\udf89 Sample Plugin Hook Executed!\')" class="flex items-center px-2.5 py-1.5 text-xs font-bold text-purple-900 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors mb-1 cursor-pointer relative z-10"><i class="fa-solid fa-gift w-5 text-purple-600 text-center"></i><span>Sample Discount Plugin</span></a>';
});
