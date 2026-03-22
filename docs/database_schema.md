# Barangay Management System - Database Schema Documentation

## Overview
The barangay management system uses a MySQL database with 10 main tables to manage residents, businesses, documents, incidents, communications, and administrative functions.

## Database Tables

### 1. users
**Purpose**: Central user management with role-based access control
**Primary Key**: `id`

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT(11) | PK, AUTO_INCREMENT | Unique user identifier |
| username | VARCHAR(50) | UNIQUE, NOT NULL | Login username |
| password | VARCHAR(255) | NOT NULL | Hashed password |
| fullname | VARCHAR(100) | NOT NULL | User's full name |
| email | VARCHAR(100) | UNIQUE, NOT NULL | Email address |
| role | ENUM | NOT NULL, DEFAULT 'resident' | User role: admin, superadmin, resident |
| created_at | DATETIME | DEFAULT CURRENT_TIMESTAMP | Account creation date |
| last_login | DATETIME | NULL | Last login timestamp |

### 2. residents
**Purpose**: Store resident profile information
**Primary Key**: `id`
**Foreign Keys**: `user_id` → `users.id`

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT(11) | PK, AUTO_INCREMENT | Unique resident identifier |
| first_name | VARCHAR(100) | NOT NULL | First name |
| middle_initial | VARCHAR(5) | NULL | Middle initial |
| last_name | VARCHAR(100) | NOT NULL | Last name |
| gender | ENUM | NOT NULL | Gender: Male, Female, Other |
| date_of_birth | DATE | NOT NULL | Birth date |
| place_of_birth | VARCHAR(255) | NOT NULL | Birth place |
| age | INT(3) | NOT NULL | Age |
| religion | VARCHAR(100) | NULL | Religion |
| citizenship | VARCHAR(100) | NOT NULL | Citizenship |
| email | VARCHAR(100) | UNIQUE, NULL | Email address |
| contact_no | VARCHAR(20) | NULL | Contact number |
| address | TEXT | NOT NULL | Residential address |
| civil_status | ENUM | NOT NULL | Civil status: Single, Married, Widowed, Separated |
| occupation | VARCHAR(100) | NULL | Occupation |
| signature_path | VARCHAR(255) | NULL | Path to signature image |
| profile_image_path | VARCHAR(255) | NULL | Path to profile image |
| id_number | VARCHAR(50) | UNIQUE, NULL | Resident ID number |
| voter_status | ENUM | NOT NULL, DEFAULT 'No' | Voter status: Yes, No |
| user_id | INT(11) | FK, NULL | Associated user account |
| created_at | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation date |
| updated_at | DATETIME | DEFAULT CURRENT_TIMESTAMP ON UPDATE | Last update timestamp |

### 3. businesses
**Purpose**: Business registration and management
**Primary Key**: `id`
**Foreign Keys**: 
- `resident_id` → `residents.id`
- `requested_by_user_id` → `users.id`

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT(11) | PK, AUTO_INCREMENT | Unique business identifier |
| resident_id | INT(11) | FK, NOT NULL | Business owner (resident) |
| business_name | VARCHAR(255) | NOT NULL | Business name |
| business_type | VARCHAR(100) | NOT NULL | Type of business |
| address | TEXT | NOT NULL | Business address |
| status | ENUM | NOT NULL, DEFAULT 'Pending' | Status: Active, Inactive, Pending |
| date_registered | DATETIME | DEFAULT CURRENT_TIMESTAMP | Registration date |
| updated_at | DATETIME | DEFAULT CURRENT_TIMESTAMP ON UPDATE | Last update timestamp |
| requested_by_user_id | INT(11) | FK, NULL | User who requested registration |

### 4. business_transactions
**Purpose**: Track business permit applications and renewals
**Primary Key**: `id`
**Foreign Keys**: `resident_id` → `residents.id`

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT(11) | PK, AUTO_INCREMENT | Unique transaction identifier |
| resident_id | INT(11) | FK, NOT NULL | Applicant resident |
| business_name | VARCHAR(255) | NOT NULL | Business name |
| business_type | VARCHAR(100) | NOT NULL | Business type |
| owner_name | VARCHAR(255) | NOT NULL | Owner's name |
| address | TEXT | NOT NULL | Business address |
| transaction_type | ENUM | NOT NULL | Type: New Permit, Renewal |
| status | ENUM | NOT NULL, DEFAULT 'Pending' | Status: Pending, Processing, Ready for Pickup, Approved, Rejected |
| application_date | DATETIME | DEFAULT CURRENT_TIMESTAMP | Application date |
| processed_date | DATETIME | NULL | Processing completion date |
| remarks | TEXT | NULL | Admin remarks |

### 5. business_permits
**Purpose**: Detailed business permit information and requirements
**Primary Key**: `id`

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT(11) | PK, AUTO_INCREMENT | Unique permit identifier |
| date_of_application | DATE | NULL | Application date |
| business_account_no | VARCHAR(255) | NULL | Business account number |
| official_receipt_no | VARCHAR(255) | NULL | Official receipt number |
| or_date | DATE | NULL | Receipt date |
| amount_paid | DECIMAL(10,2) | NULL | Amount paid |
| taxpayer_name | VARCHAR(255) | NULL | Taxpayer name |
| taxpayer_tel_no | VARCHAR(50) | NULL | Taxpayer telephone |
| taxpayer_fax_no | VARCHAR(50) | NULL | Taxpayer fax |
| taxpayer_address | TEXT | NULL | Taxpayer address |
| capital | DECIMAL(15,2) | NULL | Business capital |
| taxpayer_barangay_no | VARCHAR(50) | NULL | Barangay number |
| business_trade_name | VARCHAR(255) | NULL | Trade name |
| business_tel_no | VARCHAR(50) | NULL | Business telephone |
| comm_address_building | VARCHAR(255) | NULL | Building name |
| comm_address_no | VARCHAR(50) | NULL | Building number |
| comm_address_street | VARCHAR(255) | NULL | Street name |
| comm_address_barangay_no | VARCHAR(50) | NULL | Barangay number |
| dti_reg_no | VARCHAR(255) | NULL | DTI registration number |
| sec_reg_no | VARCHAR(255) | NULL | SEC registration number |
| num_employees | INT(11) | NULL | Number of employees |
| main_line_business | VARCHAR(255) | NULL | Main business line |
| other_line_business | TEXT | NULL | Other business lines |
| main_products_services | TEXT | NULL | Main products/services |
| other_products_services | VARCHAR(255) | NULL | Other products/services |
| ownership_type | ENUM | NULL | Ownership: single, partnership, corporation |
| proof_of_ownership | ENUM | NULL | Proof: owned, leased |
| proof_owned_reg_name | VARCHAR(255) | NULL | Registered owner name |
| proof_leased_lessor_name | VARCHAR(255) | NULL | Lessor name |
| rent_per_month | DECIMAL(10,2) | NULL | Monthly rent |
| area_sq_meter | DECIMAL(10,2) | NULL | Area in square meters |
| real_property_tax_receipt_no | VARCHAR(255) | NULL | Property tax receipt |
| has_barangay_clearance | TINYINT(1) | DEFAULT 0 | Has barangay clearance |
| has_public_liability_insurance | TINYINT(1) | DEFAULT 0 | Has liability insurance |
| insurance_company | VARCHAR(255) | NULL | Insurance company |
| insurance_date | DATE | NULL | Insurance date |
| applicant_name | VARCHAR(255) | NULL | Applicant name |
| applicant_position | VARCHAR(255) | NULL | Applicant position |
| status | ENUM | NOT NULL, DEFAULT 'Pending' | Status: Pending, Approved, Rejected |
| created_at | DATETIME | DEFAULT CURRENT_TIMESTAMP | Creation timestamp |
| updated_at | DATETIME | DEFAULT CURRENT_TIMESTAMP ON UPDATE | Update timestamp |

### 6. document_requests
**Purpose**: Manage document requests (Barangay Clearance, Certificates)
**Primary Key**: `id`
**Foreign Keys**: 
- `resident_id` → `residents.id`
- `requested_by_user_id` → `users.id`

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT(11) | PK, AUTO_INCREMENT | Unique request identifier |
| resident_id | INT(11) | FK, NOT NULL | Requesting resident |
| document_type | VARCHAR(255) | NOT NULL | Type of document |
| purpose | TEXT | NOT NULL | Purpose of request |
| details | JSON | NULL | Additional details |
| date_requested | DATETIME | DEFAULT CURRENT_TIMESTAMP | Request date |
| status | ENUM | NOT NULL, DEFAULT 'Pending' | Status: Pending, Processing, Ready for Pickup, Completed, Rejected |
| price | DECIMAL(10,2) | NULL | Document fee |
| remarks | TEXT | NULL | Admin remarks |
| requested_by_user_id | INT(11) | FK, NULL | User who made request |

### 7. incidents
**Purpose**: Track incident reports from residents
**Primary Key**: `id`
**Foreign Keys**: `resident_user_id` → `users.id`

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT(11) | PK, AUTO_INCREMENT | Unique incident identifier |
| resident_user_id | INT(11) | FK, NOT NULL | Reporting resident |
| type | VARCHAR(100) | NOT NULL | Incident type |
| location | VARCHAR(255) | NOT NULL | Incident location |
| latitude | DECIMAL(10,8) | NULL | GPS latitude |
| longitude | DECIMAL(11,8) | NULL | GPS longitude |
| description | TEXT | NOT NULL | Incident description |
| media_path | VARCHAR(255) | NULL | Path to media files |
| status | ENUM | NOT NULL, DEFAULT 'Pending' | Status: Pending, In Progress, Resolved, Rejected |
| reported_at | DATETIME | DEFAULT CURRENT_TIMESTAMP | Report timestamp |
| admin_remarks | TEXT | NULL | Admin response |

### 8. chat_messages
**Purpose**: Internal messaging system
**Primary Key**: `id`
**Foreign Keys**: 
- `sender_id` → `users.id`
- `receiver_id` → `users.id`

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT(11) | PK, AUTO_INCREMENT | Unique message identifier |
| sender_id | INT(11) | FK, NOT NULL | Message sender |
| receiver_id | INT(11) | FK, NOT NULL | Message receiver |
| message | TEXT | NOT NULL | Message content |
| sent_at | DATETIME | DEFAULT CURRENT_TIMESTAMP | Send timestamp |
| is_read | TINYINT(1) | NOT NULL, DEFAULT 0 | Read status |

### 9. announcements
**Purpose**: System announcements and notifications
**Primary Key**: `id`
**Foreign Keys**: `user_id` → `users.id`

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT(11) | PK, AUTO_INCREMENT | Unique announcement identifier |
| user_id | INT(11) | FK, NOT NULL | Author (admin) |
| title | VARCHAR(255) | NOT NULL | Announcement title |
| content | TEXT | NOT NULL | Announcement content |
| image_path | VARCHAR(255) | NULL | Path to announcement image |
| created_at | DATETIME | DEFAULT CURRENT_TIMESTAMP | Creation timestamp |

### 10. events
**Purpose**: Community events and activities
**Primary Key**: `id`
**Foreign Keys**: `created_by` → `users.id`

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT(11) | PK, AUTO_INCREMENT | Unique event identifier |
| title | VARCHAR(255) | NOT NULL | Event title |
| description | TEXT | NULL | Event description |
| location | VARCHAR(255) | NULL | Event location |
| event_date | DATE | NULL | Event date |
| event_time | TIME | NULL | Event time |
| type | ENUM | NOT NULL | Type: Upcoming Event, Regular Activity |
| created_by | INT(11) | FK, NOT NULL | Event creator |
| created_at | DATETIME | DEFAULT CURRENT_TIMESTAMP | Creation timestamp |

## Key Relationships

1. **Users ↔ Residents**: One-to-one relationship (optional)
   - A user can have one resident profile
   - A resident profile can be linked to one user account

2. **Residents ↔ Businesses**: One-to-many relationship
   - A resident can own multiple businesses
   - Each business belongs to one resident

3. **Residents ↔ Document Requests**: One-to-many relationship
   - A resident can make multiple document requests
   - Each request belongs to one resident

4. **Users ↔ Incidents**: One-to-many relationship
   - A user can report multiple incidents
   - Each incident is reported by one user

5. **Users ↔ Chat Messages**: Many-to-many relationship
   - Users can send/receive multiple messages
   - Messages have one sender and one receiver

6. **Users ↔ Announcements/Events**: One-to-many relationship
   - Admins can create multiple announcements/events
   - Each announcement/event has one creator

## Database Constraints

- **Cascade Deletes**: When a resident is deleted, their businesses and document requests are also deleted
- **Set NULL**: When a user is deleted, their resident profile link is set to NULL
- **Unique Constraints**: Email addresses, usernames, and ID numbers must be unique
- **Foreign Key Constraints**: All relationships are properly enforced with foreign keys

## Indexes

- Primary keys are automatically indexed
- Foreign key columns are indexed for performance
- Unique columns (email, username, id_number) are indexed
- Frequently queried columns (status, date fields) should be indexed

## Data Types

- **INT(11)**: For IDs and counts
- **VARCHAR**: For variable-length strings with limits
- **TEXT**: For longer text content
- **ENUM**: For predefined value sets
- **DECIMAL**: For monetary values and precise numbers
- **DATETIME**: For timestamps
- **DATE/TIME**: For date and time values
- **JSON**: For structured data storage
- **TINYINT(1)**: For boolean values 