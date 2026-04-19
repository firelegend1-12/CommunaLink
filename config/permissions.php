<?php
/**
 * Role-Based Permissions Configuration
 * Defines specific permissions for each role based on REAL barangay governance structure
 */

if (!function_exists('normalize_rbac_key')) {
    /**
     * Normalize role/permission keys to a stable lowercase format.
     *
     * @param mixed $value
     * @return string
     */
    function normalize_rbac_key($value) {
        return strtolower(trim((string) $value));
    }
}

if (!function_exists('get_rbac_role_permissions')) {
    /**
     * Return the canonical role -> permission matrix.
     *
     * @return array
     */
    function get_rbac_role_permissions() {
        global $role_permissions;
        return is_array($role_permissions) ? $role_permissions : [];
    }
}

if (!function_exists('get_rbac_supported_roles')) {
    /**
     * Return all supported role keys from the RBAC matrix.
     *
     * @return array
     */
    function get_rbac_supported_roles() {
        $matrix = get_rbac_role_permissions();
        return array_keys($matrix);
    }
}

if (!function_exists('get_rbac_supported_permissions')) {
    /**
     * Return the union of every permission key declared in the RBAC matrix.
     *
     * @return array
     */
    function get_rbac_supported_permissions() {
        static $permissions = null;

        if (is_array($permissions)) {
            return $permissions;
        }

        $matrix = get_rbac_role_permissions();
        $permission_index = [];

        foreach ($matrix as $role_entry) {
            $access = isset($role_entry['access']) && is_array($role_entry['access'])
                ? $role_entry['access']
                : [];

            foreach ($access as $permission_name => $allowed) {
                $normalized = normalize_rbac_key($permission_name);
                if ($normalized === '') {
                    continue;
                }
                $permission_index[$normalized] = true;
            }
        }

        $permissions = array_keys($permission_index);
        sort($permissions, SORT_STRING);

        return $permissions;
    }
}

if (!function_exists('rbac_is_known_role')) {
    /**
     * Check whether a role key exists in the RBAC matrix.
     *
     * @param mixed $role
     * @return bool
     */
    function rbac_is_known_role($role) {
        $normalized = normalize_rbac_key($role);
        if ($normalized === '') {
            return false;
        }

        static $role_index = null;
        if (!is_array($role_index)) {
            $role_index = array_fill_keys(get_rbac_supported_roles(), true);
        }

        return isset($role_index[$normalized]);
    }
}

if (!function_exists('rbac_is_known_permission')) {
    /**
     * Check whether a permission key exists in the RBAC matrix.
     *
     * @param mixed $permission
     * @return bool
     */
    function rbac_is_known_permission($permission) {
        $normalized = normalize_rbac_key($permission);
        if ($normalized === '') {
            return false;
        }

        static $permission_index = null;
        if (!is_array($permission_index)) {
            $permission_index = array_fill_keys(get_rbac_supported_permissions(), true);
        }

        return isset($permission_index[$normalized]);
    }
}

// Role hierarchy (higher number = higher authority) - Based on actual Philippine barangay system
function get_role_hierarchy($role) {
    $role = normalize_rbac_key($role);

    $hierarchy = [
        'resident' => 1,
        'barangay-tanod' => 2,
        'kagawad' => 3,
        'barangay-kagawad' => 3,
        'barangay-officials' => 4,
        'barangay-captain' => 4,
        'barangay-secretary' => 4,
        'barangay-treasurer' => 4,
        'admin' => 4
    ];
    return $hierarchy[$role] ?? 0;
}

// Check if a role can override another role's decision
function check_role_override($user_role, $target_role) {
    $user_role = normalize_rbac_key($user_role);
    $target_role = normalize_rbac_key($target_role);

    if ($user_role === 'admin' && $target_role !== 'admin') {
        return true;
    }

    if ($target_role === 'admin' && $user_role !== 'admin') {
        return false;
    }

    return get_role_hierarchy($user_role) > get_role_hierarchy($target_role);
}

// Role definitions with REAL-LIFE permissions based on Philippine barangay governance
$role_permissions = [
    'admin' => [
        'description' => 'System Administrator - Full system control',
        'access' => [
            'user_management' => true,
            'system_logs' => true,
            'all_pages' => true,
            'delete_users' => true,
            'view_logs' => true,
            'manage_announcements' => true,
            'manage_events' => true,
            'manage_incidents' => true,
            'manage_documents' => true,
            'manage_businesses' => true,
            'manage_residents' => true,
            'view_residents' => true,
            'edit_resident_profile' => true,
            'view_monitoring_requests' => true,
            'access_chat' => true,
            'financial_management' => true,
            'approve_applications' => true,
            'override_decisions' => true
        ],
        'restricted' => []
    ],

    'barangay-officials' => [
        'description' => 'Barangay Officials umbrella tier (community board, incident, document, chat, residents view-only)',
        'access' => [
            'user_management' => false,
            'system_logs' => false,
            'all_pages' => false,
            'delete_users' => false,
            'view_logs' => false,
            'manage_announcements' => true,
            'manage_events' => true,
            'manage_incidents' => true,
            'manage_documents' => true,
            'manage_businesses' => false,
            'manage_residents' => false,
            'view_residents' => true,
            'edit_resident_profile' => false,
            'view_monitoring_requests' => false,
            'access_chat' => true,
            'financial_management' => false,
            'approve_applications' => false,
            'override_decisions' => false,
            'preside_meetings' => false,
            'emergency_powers' => false
        ],
        'restricted' => ['user_management', 'system_logs', 'all_pages', 'delete_users', 'view_logs', 'manage_businesses', 'manage_residents', 'edit_resident_profile', 'view_monitoring_requests', 'financial_management', 'approve_applications', 'override_decisions', 'preside_meetings', 'emergency_powers']
    ],
    
    'barangay-captain' => [
        'description' => 'Barangay Captain - Official tier access profile',
        'access' => [
            'user_management' => false,
            'system_logs' => false,
            'all_pages' => false,
            'view_logs' => false,
            'delete_users' => false,
            'manage_announcements' => true,
            'manage_events' => true,
            'manage_incidents' => true,
            'manage_documents' => true,
            'manage_businesses' => false,
            'manage_residents' => false,
            'view_residents' => true,
            'edit_resident_profile' => false,
            'view_monitoring_requests' => false,
            'access_chat' => true,
            'financial_management' => false,
            'approve_applications' => false,
            'override_decisions' => false,
            'preside_meetings' => false,
            'emergency_powers' => false
        ],
        'restricted' => ['user_management', 'system_logs', 'all_pages', 'delete_users', 'view_logs', 'manage_businesses', 'manage_residents', 'edit_resident_profile', 'view_monitoring_requests', 'financial_management', 'approve_applications', 'override_decisions', 'preside_meetings', 'emergency_powers']
    ],
    
    'kagawad' => [
        'description' => 'Barangay Kagawad - Incident, chat, monitoring, residents view-only access',
        'access' => [
            'user_management' => false,
            'system_logs' => false,
            'all_pages' => false,
            'view_logs' => false,
            'delete_users' => false,
            'manage_announcements' => false,
            'manage_events' => false,
            'manage_incidents' => true,
            'manage_documents' => false,
            'manage_businesses' => false,
            'manage_residents' => false,
            'view_residents' => true,
            'edit_resident_profile' => false,
            'view_monitoring_requests' => true,
            'access_chat' => true,
            'financial_management' => false,
            'approve_applications' => false,
            'override_decisions' => false,
            'preside_meetings' => false,
            'emergency_powers' => false,
            'committee_management' => false
        ],
        'restricted' => ['user_management', 'system_logs', 'all_pages', 'delete_users', 'view_logs', 'manage_announcements', 'manage_events', 'manage_documents', 'manage_businesses', 'manage_residents', 'edit_resident_profile', 'financial_management', 'approve_applications', 'override_decisions', 'preside_meetings', 'emergency_powers', 'committee_management']
    ],

    'barangay-kagawad' => [
        'description' => 'Barangay Kagawad alias role key with the same access profile',
        'access' => [
            'user_management' => false,
            'system_logs' => false,
            'all_pages' => false,
            'view_logs' => false,
            'delete_users' => false,
            'manage_announcements' => false,
            'manage_events' => false,
            'manage_incidents' => true,
            'manage_documents' => false,
            'manage_businesses' => false,
            'manage_residents' => false,
            'view_residents' => true,
            'edit_resident_profile' => false,
            'view_monitoring_requests' => true,
            'access_chat' => true,
            'financial_management' => false,
            'approve_applications' => false,
            'override_decisions' => false,
            'preside_meetings' => false,
            'emergency_powers' => false,
            'committee_management' => false
        ],
        'restricted' => ['user_management', 'system_logs', 'all_pages', 'delete_users', 'view_logs', 'manage_announcements', 'manage_events', 'manage_documents', 'manage_businesses', 'manage_residents', 'edit_resident_profile', 'financial_management', 'approve_applications', 'override_decisions', 'preside_meetings', 'emergency_powers', 'committee_management']
    ],
    
    'barangay-secretary' => [
        'description' => 'Barangay Secretary - Official tier access profile',
        'access' => [
            'user_management' => false,
            'system_logs' => false,
            'all_pages' => false,
            'view_logs' => false,
            'delete_users' => false,
            'manage_announcements' => true,
            'manage_events' => true,
            'manage_incidents' => true,
            'manage_documents' => true,
            'manage_businesses' => false,
            'manage_residents' => false,
            'view_residents' => true,
            'edit_resident_profile' => false,
            'view_monitoring_requests' => false,
            'access_chat' => true,
            'financial_management' => false,
            'approve_applications' => false,
            'override_decisions' => false,
            'preside_meetings' => false,
            'emergency_powers' => false,
            'record_keeping' => true,
            'meeting_minutes' => true
        ],
        'restricted' => ['user_management', 'system_logs', 'all_pages', 'delete_users', 'view_logs', 'manage_businesses', 'manage_residents', 'edit_resident_profile', 'view_monitoring_requests', 'financial_management', 'approve_applications', 'override_decisions', 'preside_meetings', 'emergency_powers']
    ],
    
    'barangay-treasurer' => [
        'description' => 'Barangay Treasurer - Official tier access profile with financial tasks',
        'access' => [
            'user_management' => false,
            'system_logs' => false,
            'all_pages' => false,
            'view_logs' => false,
            'delete_users' => false,
            'manage_announcements' => true,
            'manage_events' => true,
            'manage_incidents' => true,
            'manage_documents' => true,
            'manage_businesses' => false,
            'manage_residents' => false,
            'view_residents' => true,
            'edit_resident_profile' => false,
            'view_monitoring_requests' => false,
            'access_chat' => true,
            'financial_management' => true,
            'approve_applications' => false,
            'override_decisions' => false,
            'preside_meetings' => false,
            'emergency_powers' => false,
            'budget_management' => true,
            'financial_reports' => true,
            'tax_collection' => true,
            'expense_tracking' => true
        ],
        'restricted' => ['user_management', 'system_logs', 'all_pages', 'delete_users', 'view_logs', 'manage_businesses', 'manage_residents', 'edit_resident_profile', 'view_monitoring_requests', 'approve_applications', 'override_decisions', 'preside_meetings', 'emergency_powers']
    ],
    
    'barangay-tanod' => [
        'description' => 'Barangay Tanod - Incident reports and chat access',
        'access' => [
            'user_management' => false,
            'system_logs' => false,
            'all_pages' => false,
            'view_logs' => false,
            'delete_users' => false,
            'manage_announcements' => false,
            'manage_events' => false,
            'manage_incidents' => true,
            'manage_documents' => false,
            'manage_businesses' => false,
            'manage_residents' => false,
            'view_residents' => false,
            'edit_resident_profile' => false,
            'view_monitoring_requests' => false,
            'access_chat' => true,
            'financial_management' => false,
            'approve_applications' => false,
            'override_decisions' => false,
            'preside_meetings' => false,
            'emergency_powers' => false,
            'patrol_management' => true,
            'incident_reporting' => true,
            'peace_and_order' => true,
            'emergency_response' => true
        ],
        'restricted' => ['user_management', 'system_logs', 'all_pages', 'delete_users', 'view_logs', 'manage_announcements', 'manage_events', 'manage_documents', 'manage_businesses', 'manage_residents', 'view_residents', 'edit_resident_profile', 'view_monitoring_requests', 'financial_management', 'approve_applications', 'override_decisions', 'preside_meetings', 'emergency_powers']
    ],
    
    'resident' => [
        'description' => 'Regular resident - Resident interface only',
        'access' => [
            'user_management' => false,
            'system_logs' => false,
            'all_pages' => false,
            'view_logs' => false,
            'delete_users' => false,
            'manage_announcements' => false,
            'manage_events' => false,
            'manage_incidents' => false,
            'manage_documents' => false,
            'manage_businesses' => false,
            'manage_residents' => false,
            'view_residents' => false,
            'edit_resident_profile' => false,
            'view_monitoring_requests' => false,
            'access_chat' => false,
            'financial_management' => false,
            'approve_applications' => false,
            'override_decisions' => false,
            'preside_meetings' => false,
            'emergency_powers' => false,
            'view_announcements' => true,        // Can view announcements
            'view_events' => true,               // Can view events
            'submit_applications' => true,       // Can submit applications
            'view_documents' => true,            // Can view public documents
            'report_incidents' => true,          // Can report incidents
            'access_services' => true            // Can access basic services
        ],
        'restricted' => ['user_management', 'system_logs', 'all_pages', 'delete_users', 'view_logs', 'manage_announcements', 'manage_events', 'manage_incidents', 'manage_documents', 'manage_businesses', 'manage_residents', 'view_residents', 'edit_resident_profile', 'view_monitoring_requests', 'access_chat', 'financial_management', 'approve_applications', 'override_decisions', 'preside_meetings', 'emergency_powers']
    ]
];

// Helper functions for permission checking
function can_access($user_role, $permission) {
    $user_role = normalize_rbac_key($user_role);
    $permission = normalize_rbac_key($permission);

    if (!rbac_is_known_role($user_role) || !rbac_is_known_permission($permission)) {
        return false;
    }

    $role_permissions_map = get_rbac_role_permissions();
    
    return (bool) ($role_permissions_map[$user_role]['access'][$permission] ?? false);
}

function is_barangay_official($user_role) {
    $user_role = normalize_rbac_key($user_role);
    return in_array($user_role, [
        'barangay-officials',
        'barangay-captain', 
        'kagawad', 
        'barangay-kagawad',
        'barangay-secretary', 
        'barangay-treasurer', 
        'barangay-tanod'
    ], true);
}

function is_barangay_captain($user_role) {
    return normalize_rbac_key($user_role) === 'barangay-captain';
}

function can_final_approve_documents($user_role) {
    return in_array(normalize_rbac_key($user_role), ['admin'], true);
}

function can_manage_incidents($user_role) {
    return in_array(normalize_rbac_key($user_role), ['admin', 'barangay-officials', 'barangay-captain', 'kagawad', 'barangay-kagawad', 'barangay-secretary', 'barangay-treasurer', 'barangay-tanod'], true);
}

function can_manage_businesses($user_role) {
    return in_array(normalize_rbac_key($user_role), ['admin'], true);
}

function can_manage_documents($user_role) {
    return in_array(normalize_rbac_key($user_role), ['admin', 'barangay-officials', 'barangay-captain', 'barangay-secretary', 'barangay-treasurer'], true);
}

function can_manage_announcements($user_role) {
    return in_array(normalize_rbac_key($user_role), ['admin', 'barangay-officials', 'barangay-captain', 'barangay-secretary', 'barangay-treasurer'], true);
}

function can_manage_events($user_role) {
    return in_array(normalize_rbac_key($user_role), ['admin', 'barangay-officials', 'barangay-captain', 'barangay-secretary', 'barangay-treasurer'], true);
}

function can_manage_finances($user_role) {
    return in_array(normalize_rbac_key($user_role), ['admin', 'barangay-treasurer'], true);
}

function can_preside_meetings($user_role) {
    return in_array(normalize_rbac_key($user_role), ['admin'], true);
}

function can_override_decisions($user_role) {
    return in_array(normalize_rbac_key($user_role), ['admin'], true);
}

function can_handle_emergencies($user_role) {
    return in_array(normalize_rbac_key($user_role), ['admin'], true);
}





