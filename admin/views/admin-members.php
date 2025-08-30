<?php
if (!defined('ABSPATH')) {
    exit;
}

// Prepare list table
$list_table = new UNIVGA_Members_List_Table();
$list_table->prepare_items();
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Members', UNIVGA_TEXT_DOMAIN); ?></h1>
    
    <hr class="wp-header-end">
    
    <form method="get">
        <input type="hidden" name="page" value="univga-members">
        <?php $list_table->search_box(__('Search Members', UNIVGA_TEXT_DOMAIN), 'members'); ?>
    </form>
    
    <form method="post">
        <?php 
        $list_table->display(); 
        ?>
    </form>
</div>

<style>
.column-avg_progress {
    text-align: center;
}
.column-enrolled_courses {
    text-align: center;
}
</style>
