<?php
/**
 * Role-Based Permissions Configuration
 * Defines specific permissions for each role based on REAL barangay governance structure
 */

// Role hierarchy (higher number = higher authority) - Based on actual Philippine barangay system
function get_role_hierarchy($role) {
    $hierarchy = [
        'resident' => 1,
        'barangay-tanod' => 2,
        'barangay-treasurer' => 3,
        'barangay-secretary' => 4,
        'kagawad' => 5,
        'barangay-captain' => 6,
        'admin' => 7
    ];
    return $hierarchy[$role] ?? 0;
}

// Check if a role can override another role's decision
function check_role_override($user_role, $target_role) {
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
            'financial_management' => true,
            'approve_applications' => true,
            'override_decisions' => true
        ],
        'restricted' => []
    ],
    
    'barangay-captain' => [
        'description' => 'Barangay Captain - Chief Executive Officer of the barangay',
        'access' => [
            'user_management' => false,
            'system_logs' => false,
            'view_logs' => false,
            'delete_users' => false,
            'manage_announcements' => true,      // Can make official announcements
            'manage_events' => true,             // Can organize barangay events
            'manage_incidents' => true,          // Can handle peace and order issues
            'manage_documents' => true,          // Can issue official documents
            'manage_businesses' => true,         // Can approve business permits
            'manage_residents' => true,          // Can manage resident records
            'financial_management' => true,      // Can oversee financial matters
            'approve_applications' => true,      // Final approval for applications
            'override_decisions' => true,        // Can override other officials' decisions
            'preside_meetings' => true,          // Presides over barangay council meetings
            'emergency_powers' => true           // Emergency decision-making powers
        ],
        'restricted' => ['user_management', 'system_logs', 'delete_users']
    ],
    
    'kagawad' => [
        'description' => 'Barangay Kagawad - Elected council member with specific committees',
        'access' => [
            'user_management' => false,
            'system_logs' => false,
            'view_logs' => false,
            'delete_users' => false,
            'manage_announcements' => true,      // Can make announcements within their committee
            'manage_events' => true,             // Can organize events for their committee
            'manage_incidents' => true,          // Can handle incidents in their area
            'manage_documents' => true,          // Can process documents
            'manage_businesses' => true,         // Can process business applications
            'manage_residents' => true,          // Can assist with resident matters
            'financial_management' => false,     // No direct financial control
            'approve_applications' => false,     // Can recommend but not final approve
            'override_decisions' => false,       // Cannot override captain's decisions
            'preside_meetings' => false,         // Cannot preside over meetings
            'emergency_powers' => false,         // No emergency powers
            'committee_management' => true       // Can manage their assigned committee
        ],
        'restricted' => ['user_management', 'system_logs', 'delete_users', 'financial_management', 'approve_applications', 'override_decisions', 'preside_meetings', 'emergency_powers']
    ],
    
    'barangay-secretary' => [
        'description' => 'Barangay Secretary - Administrative and record-keeping officer',
        'access' => [
            'user_management' => false,
            'system_logs' => false,
            'view_logs' => false,
            'delete_users' => false,
            'manage_announcements' => true,      // Can post official announcements
            'manage_events' => true,             // Can organize administrative events
            'manage_incidents' => false,         // Not involved in peace and order
            'manage_documents' => true,          // Primary responsibility - document management
            'manage_businesses' => true,         // Can process business applications
            'manage_residents' => true,          // Can maintain resident records
            'financial_management' => false,     // No financial control
            'approve_applications' => false,     // Can process but not approve
            'override_decisions' => false,       // Cannot override decisions
            'preside_meetings' => false,         // Cannot preside
            'emergency_powers' => false,         // No emergency powers
            'record_keeping' => true,            // Primary duty - record keeping
            'meeting_minutes' => true            // Can take and maintain meeting minutes
        ],
        'restricted' => ['user_management', 'system_logs', 'delete_users', 'manage_incidents', 'financial_management', 'approve_applications', 'override_decisions', 'preside_meetings', 'emergency_powers']
    ],
    
    'barangay-treasurer' => [
        'description' => 'Barangay Treasurer - Financial and fiscal management officer',
        'access' => [
            'user_management' => false,
            'system_logs' => false,
            'view_logs' => false,
            'delete_users' => false,
            'manage_announcements' => false,     // Not responsible for announcements
            'manage_events' => false,            // Not responsible for events
            'manage_incidents' => false,         // Not involved in peace and order
            'manage_documents' => false,         // Not primary document handler
            'manage_businesses' => true,         // Can process business permits (financial aspect)
            'manage_residents' => false,         // Not primary resident manager
            'financial_management' => true,      // PRIMARY RESPONSIBILITY
            'approve_applications' => false,     // Can process financial aspects but not final approve
            'override_decisions' => false,       // Cannot override decisions
            'preside_meetings' => false,         // Cannot preside
            'emergency_powers' => false,         // No emergency powers
            'budget_management' => true,         // Can manage barangay budget
            'financial_reports' => true,         // Can generate financial reports
            'tax_collection' => true,            // Can handle tax collection
            'expense_tracking' => true           // Can track expenses
        ],
        'restricted' => ['user_management', 'system_logs', 'delete_users', 'manage_announcements', 'manage_events', 'manage_incidents', 'manage_documents', 'manage_residents', 'approve_applications', 'override_decisions', 'preside_meetings', 'emergency_powers']
    ],
    
    'barangay-tanod' => [
        'description' => 'Barangay Tanod - Peace and order officer',
        'access' => [
            'user_management' => false,
            'system_logs' => false,
            'view_logs' => false,
            'delete_users' => false,
            'manage_announcements' => false,     // Not responsible for announcements
            'manage_events' => false,            // Not responsible for events
            'manage_incidents' => true,          // PRIMARY RESPONSIBILITY
            'manage_documents' => false,         // Not primary document handler
            'manage_businesses' => false,        // Not involved in business permits
            'manage_residents' => false,         // Not primary resident manager
            'financial_management' => false,     // No financial control
            'approve_applications' => false,     // Cannot approve applications
            'override_decisions' => false,       // Cannot override decisions
            'preside_meetings' => false,         // Cannot preside
            'emergency_powers' => false,         // No emergency powers
            'patrol_management' => true,         // Can manage patrol schedules
            'incident_reporting' => true,        // Can report incidents
            'peace_and_order' => true,           // Primary duty - peace and order
            'emergency_response' => true         // Can respond to emergencies
        ],
        'restricted' => ['user_management', 'system_logs', 'delete_users', 'manage_announcements', 'manage_events', 'manage_documents', 'manage_businesses', 'manage_residents', 'financial_management', 'approve_applications', 'override_decisions', 'preside_meetings', 'emergency_powers']
    ],
    
    'resident' => [
        'description' => 'Regular resident - Basic access to services',
        'access' => [
            'user_management' => false,
            'system_logs' => false,
            'view_logs' => false,
            'delete_users' => false,
            'manage_announcements' => false,
            'manage_events' => false,
            'manage_incidents' => false,
            'manage_documents' => false,
            'manage_businesses' => false,
            'manage_residents' => false,
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
        'restricted' => ['user_management', 'system_logs', 'delete_users', 'manage_announcements', 'manage_events', 'manage_incidents', 'manage_documents', 'manage_businesses', 'manage_residents', 'financial_management', 'approve_applications', 'override_decisions', 'preside_meetings', 'emergency_powers']
    ]
];

// Helper functions for permission checking
function can_access($user_role, $permission) {
    global $role_permissions;
    
    if (!isset($role_permissions[$user_role])) {
        return false;
    }
    
    return $role_permissions[$user_role]['access'][$permission] ?? false;
}

function is_barangay_official($user_role) {
    return in_array($user_role, [
        'barangay-captain', 
        'kagawad', 
        'barangay-secretary', 
        'barangay-treasurer', 
        'barangay-tanod'
    ]);
}

function is_barangay_captain($user_role) {
    return $user_role === 'barangay-captain';
}

function can_final_approve_documents($user_role) {
    return in_array($user_role, ['admin', 'barangay-captain']);
}

function can_manage_incidents($user_role) {
    return in_array($user_role, ['admin', 'barangay-captain', 'kagawad', 'barangay-tanod']);
}

function can_manage_businesses($user_role) {
    return in_array($user_role, ['admin', 'barangay-captain', 'kagawad', 'barangay-secretary', 'barangay-treasurer']);
}

function can_manage_documents($user_role) {
    return in_array($user_role, ['admin', 'barangay-captain', 'kagawad', 'barangay-secretary']);
}

function can_manage_announcements($user_role) {
    return in_array($user_role, ['admin', 'barangay-captain', 'kagawad', 'barangay-secretary']);
}

function can_manage_events($user_role) {
    return in_array($user_role, ['admin', 'barangay-captain', 'kagawad', 'barangay-secretary']);
}

function can_manage_finances($user_role) {
    return in_array($user_role, ['admin', 'barangay-captain', 'barangay-treasurer']);
}

function can_preside_meetings($user_role) {
    return in_array($user_role, ['admin', 'barangay-captain']);
}

function can_override_decisions($user_role) {
    return in_array($user_role, ['admin', 'barangay-captain']);
}

function can_handle_emergencies($user_role) {
    return in_array($user_role, ['admin', 'barangay-captain']);
}





