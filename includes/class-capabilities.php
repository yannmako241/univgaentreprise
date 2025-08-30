<?php

/**
 * Capabilities management
 */
class UNIVGA_Capabilities {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // No hooks needed for now, capabilities are handled during role checks
    }
    
    /**
     * Check if user can manage organization
     */
    public static function can_manage_org($user_id, $org_id) {
        $user = get_userdata($user_id);
        
        if (!$user) {
            return false;
        }
        
        // Administrators can manage all orgs
        if (user_can($user, 'manage_options') || user_can($user, 'univga_admin_access')) {
            return true;
        }
        
        // Organization managers can only manage their own org
        if (user_can($user, 'univga_org_manage')) {
            $member = UNIVGA_Members::get_user_org_membership($user_id);
            return $member && $member->org_id == $org_id;
        }
        
        return false;
    }
    
    /**
     * Check if user can manage team
     */
    public static function can_manage_team($user_id, $team_id) {
        $user = get_userdata($user_id);
        
        if (!$user) {
            return false;
        }
        
        // Administrators can manage all teams
        if (user_can($user, 'manage_options') || user_can($user, 'univga_admin_access')) {
            return true;
        }
        
        // Get team details
        $team = UNIVGA_Teams::get($team_id);
        if (!$team) {
            return false;
        }
        
        // Organization managers can manage teams in their org
        if (user_can($user, 'univga_org_manage')) {
            $member = UNIVGA_Members::get_user_org_membership($user_id);
            return $member && $member->org_id == $team->org_id;
        }
        
        // Team leaders can manage their own team
        if (user_can($user, 'univga_team_manage')) {
            return $team->manager_user_id == $user_id;
        }
        
        return false;
    }
    
    /**
     * Get user's organization ID
     */
    public static function get_user_org_id($user_id) {
        $member = UNIVGA_Members::get_user_org_membership($user_id);
        return $member ? $member->org_id : null;
    }
}
