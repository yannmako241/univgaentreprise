<?php
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submissions
if (isset($_POST['submit'])) {
    if (isset($_POST['team_id'])) {
        // Edit team
        check_admin_referer('edit_team');
        
        $team_id = intval($_POST['team_id']);
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'manager_user_id' => intval($_POST['manager_user_id']) ?: null,
        );
        
        $result = UNIVGA_Teams::update($team_id, $data);
        
        if ($result) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Team updated successfully.', UNIVGA_TEXT_DOMAIN) . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to update team.', UNIVGA_TEXT_DOMAIN) . '</p></div>';
        }
    } else {
        // Create team
        check_admin_referer('create_team');
        
        $data = array(
            'org_id' => intval($_POST['org_id']),
            'name' => sanitize_text_field($_POST['name']),
            'manager_user_id' => intval($_POST['manager_user_id']) ?: null,
        );
        
        $result = UNIVGA_Teams::create($data);
        
        if (!is_wp_error($result)) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Team created successfully.', UNIVGA_TEXT_DOMAIN) . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . $result->get_error_message() . '</p></div>';
        }
    }
}

// Get team for editing
$editing_team = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['team_id'])) {
    $editing_team = UNIVGA_Teams::get(intval($_GET['team_id']));
}

$show_form = isset($_GET['action']) && in_array($_GET['action'], array('create', 'edit'));
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Teams', UNIVGA_TEXT_DOMAIN); ?></h1>
    
    <?php if (!$show_form): ?>
    <a href="<?php echo add_query_arg('action', 'create'); ?>" class="page-title-action">
        <?php _e('Add New', UNIVGA_TEXT_DOMAIN); ?>
    </a>
    <?php endif; ?>
    
    <hr class="wp-header-end">
    
    <?php if ($show_form): ?>
    
    <div class="univga-form-container">
        <h2><?php echo $editing_team ? __('Edit Team', UNIVGA_TEXT_DOMAIN) : __('Add New Team', UNIVGA_TEXT_DOMAIN); ?></h2>
        
        <form method="post" action="">
            <?php 
            if ($editing_team) {
                wp_nonce_field('edit_team');
                echo '<input type="hidden" name="team_id" value="' . $editing_team->id . '">';
            } else {
                wp_nonce_field('create_team');
            }
            ?>
            
            <table class="form-table">
                <?php if (!$editing_team): ?>
                <tr>
                    <th scope="row">
                        <label for="org_id"><?php _e('Organization', UNIVGA_TEXT_DOMAIN); ?> *</label>
                    </th>
                    <td>
                        <select name="org_id" id="org_id" required>
                            <?php echo univga_get_organization_options(isset($_GET['org_id']) ? intval($_GET['org_id']) : 0); ?>
                        </select>
                    </td>
                </tr>
                <?php endif; ?>
                
                <tr>
                    <th scope="row">
                        <label for="name"><?php _e('Team Name', UNIVGA_TEXT_DOMAIN); ?> *</label>
                    </th>
                    <td>
                        <input type="text" name="name" id="name" 
                               value="<?php echo $editing_team ? esc_attr($editing_team->name) : ''; ?>" 
                               class="regular-text" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="manager_user_id"><?php _e('Team Manager', UNIVGA_TEXT_DOMAIN); ?></label>
                    </th>
                    <td>
                        <?php
                        wp_dropdown_users(array(
                            'name' => 'manager_user_id',
                            'id' => 'manager_user_id',
                            'selected' => $editing_team ? $editing_team->manager_user_id : 0,
                            'show_option_none' => __('Select user...', UNIVGA_TEXT_DOMAIN),
                            'option_none_value' => '',
                        ));
                        ?>
                        <p class="description"><?php _e('User who will manage this team', UNIVGA_TEXT_DOMAIN); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="submit" class="button-primary" 
                       value="<?php echo $editing_team ? __('Update Team', UNIVGA_TEXT_DOMAIN) : __('Create Team', UNIVGA_TEXT_DOMAIN); ?>">
                <a href="<?php echo univga_get_admin_url('univga-teams'); ?>" class="button-secondary">
                    <?php _e('Cancel', UNIVGA_TEXT_DOMAIN); ?>
                </a>
            </p>
        </form>
    </div>
    
    <?php else: ?>
    
    <?php
    // Prepare list table
    $list_table = new UNIVGA_Teams_List_Table();
    $list_table->prepare_items();
    ?>
    
    <form method="get">
        <input type="hidden" name="page" value="univga-teams">
        <?php $list_table->search_box(__('Search Teams', UNIVGA_TEXT_DOMAIN), 'teams'); ?>
    </form>
    
    <form method="post">
        <?php $list_table->display(); ?>
    </form>
    
    <?php endif; ?>
</div>
