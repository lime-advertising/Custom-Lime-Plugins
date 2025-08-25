# CareerNest Plugin — Feature Documentation

This document provides comprehensive documentation of all implemented features in the CareerNest job portal plugin.

## Table of Contents

1. [Template Routing System](#template-routing-system)
2. [Guest Job Application System](#guest-job-application-system)
3. [Applicant Dashboard](#applicant-dashboard)
4. [Profile Management System](#profile-management-system)
5. [Frontend Editing Experience](#frontend-editing-experience)
6. [Data Structures](#data-structures)
7. [Asset Management](#asset-management)
8. [Security Implementation](#security-implementation)

---

## Template Routing System

### Overview

Comprehensive template routing system that ensures CareerNest templates load correctly regardless of the active WordPress theme.

### Implementation

- **File**: `includes/class-plugin.php`
- **Hook**: `template_include` filter
- **Method**: `load_template()`

### Features

- **Page Detection**: Automatically detects CareerNest pages using stored page IDs
- **CPT Template Mapping**: Maps custom post types to their respective single templates
- **Template Hierarchy**: Follows WordPress template hierarchy with plugin fallbacks
- **Conditional Asset Loading**: Loads page-specific CSS/JS only when needed

### Template Mapping

```php
// Page Templates
'jobs' => 'template-jobs.php'
'employer-dashboard' => 'template-employer-dashboard.php'
'applicant-dashboard' => 'template-applicant-dashboard.php'
'register-employer' => 'template-register-employer.php'
'register-applicant' => 'template-register-applicant.php'
'apply-job' => 'template-apply-job.php'

// CPT Single Templates
'job_listing' => 'single-job_listing.php'
'employer' => 'single-employer.php'
'applicant' => 'single-applicant.php'
```

### Asset Loading Strategy

- **Applicant Dashboard**: Loads `applicant-dashboard.css` and `applicant-dashboard.js`
- **Admin Pages**: Loads `admin.css` and `admin.js` with Maps API when needed
- **Conditional Loading**: Assets only load on relevant pages to optimize performance

---

## Guest Job Application System

### Overview

Complete guest application system allowing non-registered users to apply for jobs with automatic account creation and email notifications.

### Implementation

- **File**: `templates/template-apply-job.php`
- **Integration**: `includes/class-plugin.php` (user linking system)

### Features

#### 1. Guest Application Form

- **Job Information Display**: Shows job title, company, location, and description
- **Application Fields**:
  - Full name, email, phone, location
  - Cover letter textarea
  - Resume file upload with validation
- **Guest Functionality**: No registration required to apply

#### 2. Automatic Account Creation

- **User Creation**: Automatically creates WordPress user account for guest applicants
- **Role Assignment**: Assigns 'applicant' role to new users
- **Data Sanitization**: All user data properly sanitized before account creation
- **Duplicate Handling**: Handles existing email addresses gracefully

#### 3. Email Notification System

- **Password Reset Email**: Sends email with password reset link to new users
- **Custom Email Filter**: Uses `wp_new_user_notification_email` filter for customization
- **Professional Messaging**: Clear instructions for account access

#### 4. File Upload Handling

- **Resume Upload**: Secure file upload with validation
- **File Type Validation**: Restricts to PDF, DOC, DOCX formats
- **Size Limits**: Enforces file size restrictions
- **Security**: Proper file handling with WordPress media functions

#### 5. Application Linking System

- **User Registration Hook**: Uses `user_register` hook to link applications
- **Automatic Linking**: Connects guest applications to newly created user accounts
- **Profile Creation**: Creates applicant CPT post linked to user account

### Data Flow

1. Guest fills application form
2. Application data validated and sanitized
3. Job application CPT created with guest data
4. User account created automatically
5. Email sent with password reset link
6. Application linked to user account via hook
7. Applicant profile created and linked

---

## Applicant Dashboard

### Overview

Comprehensive applicant dashboard providing application tracking, profile management, statistics, and job recommendations.

### Implementation

- **File**: `templates/template-applicant-dashboard.php`
- **Assets**: `assets/css/applicant-dashboard.css`, `assets/js/applicant-dashboard.js`

### Features

#### 1. Dashboard Header

- **Welcome Message**: Personalized greeting with user name
- **User Information**: Professional title and location display
- **Action Buttons**: View Public Profile, Edit Profile, Logout

#### 2. Statistics Cards

- **Total Applications**: Count of all submitted applications
- **Active Applications**: New and interviewed applications
- **Interviews**: Applications in interview stage
- **Offers/Hired**: Successful applications

#### 3. Application Tracking

- **Application List**: Displays all user applications with details
- **Status Badges**: Color-coded status indicators
- **Job Information**: Job title, company, application date
- **Resume Links**: Direct links to uploaded resumes
- **Action Buttons**: View application details

#### 4. Profile Sections (Display Mode)

- **Personal Summary**: Rich text display from post content
- **Work Experience**: Up to 5 positions with company, title, dates, descriptions
- **Education**: Up to 5 qualifications with institution, degree, completion status
- **Licenses & Certifications**: Up to 5 certifications with issuer, expiry, credential IDs
- **Skills**: Tag-based display with overflow indicators
- **Websites & Social Profiles**: LinkedIn and custom links

#### 5. Job Recommendations

- **Smart Recommendations**: Based on user skills and work preferences
- **Job Details**: Title, company, location
- **Direct Links**: Links to job listings

#### 6. Empty States

- **No Applications**: Encouraging message with "Browse Jobs" link
- **Professional Design**: Clean, user-friendly empty state messaging

---

## Profile Management System

### Overview

Comprehensive profile management system with structured data storage and validation.

### Data Structures

#### Basic Profile Information

```php
// User-level data
$user->display_name
$user->user_email

// Applicant meta fields
'_professional_title' => string
'_phone' => string
'_location' => string
'_right_to_work' => enum('foreign', 'australian')
'_work_types' => array('full_time', 'part_time', 'contract', etc.)
'_available_for_work' => boolean
'_skills' => array of strings
'_linkedin_url' => url
```

#### Education Data Structure

```php
'_education' => [
    [
        'institution' => string,
        'certification' => string,
        'end_date' => string (YYYY-MM format),
        'complete' => boolean
    ],
    // ... unlimited entries
]
```

#### Work Experience Data Structure

```php
'_experience' => [
    [
        'company' => string,
        'title' => string,
        'start_date' => string (YYYY-MM format),
        'end_date' => string (YYYY-MM format),
        'current' => boolean,
        'description' => text
    ],
    // ... unlimited entries
]
```

#### Licenses & Certifications Data Structure

```php
'_licenses' => [
    [
        'name' => string,
        'issuer' => string,
        'issue_date' => string (YYYY-MM format),
        'expiry_date' => string (YYYY-MM format),
        'credential_id' => string
    ],
    // ... unlimited entries
]
```

#### Websites & Links Data Structure

```php
'_links' => [
    [
        'label' => string,
        'url' => url
    ],
    // ... unlimited entries
]
```

### Form Processing

- **Function**: `process_profile_update()`
- **Security**: Nonce verification and capability checks
- **Sanitization**: Comprehensive data sanitization for all field types
- **Validation**: Required field validation and data type checking
- **Storage**: WordPress post meta with structured arrays

---

## Frontend Editing Experience

### Overview

Modern in-place editing system that provides seamless transition between viewing and editing profile information.

### User Interface

#### 1. Header Controls

- **View Public Profile**: Opens public profile in new tab (cn-btn-secondary)
- **Edit Profile**: Toggles edit mode (cn-btn-primary)
- **Logout**: Standard logout functionality (cn-btn-outline)

#### 2. Edit Mode Behavior

- **Display Replacement**: Profile display sections hidden, edit forms shown in their place
- **Form Sections**: Organized into logical sections (Basic Info, Personal Summary, Education, etc.)
- **Button State**: "Edit Profile" changes to "Cancel Edit"

#### 3. Form Sections

##### Basic Information

- Full Name (required), Professional Title, Phone, Location
- Right to Work dropdown, Work Preferences checkbox grid
- Skills input with comma separation, Availability checkbox

##### Personal Summary

- Large textarea (6 rows) for career summary
- Integrated with WordPress post content

##### Education (Dynamic Repeater)

- Institution, Degree/Certification, Completion Date
- Completion status checkbox
- Add/Remove buttons for unlimited entries

##### Work Experience (Dynamic Repeater)

- Company, Job Title, Start Date, End Date
- Current Position checkbox (disables end date)
- Job Description textarea (4 rows)
- Add/Remove buttons for unlimited entries

##### Licenses & Certifications (Dynamic Repeater)

- Name, Issuing Organization, Issue Date, Expiry Date
- Credential ID field
- Add/Remove buttons for unlimited entries

##### Websites & Social Profiles (Dynamic Repeater)

- Label field (e.g., "Portfolio", "GitHub")
- URL field with validation
- Add/Remove buttons for unlimited entries

#### 4. Form Actions

- **Save Changes**: Submits form and returns to display mode
- **Cancel**: Returns to display mode without saving

### JavaScript Functionality

#### 1. Edit Mode Toggle

```javascript
function enterEditMode() {
  // Hide display sections
  // Show edit form
  // Update button text
}

function exitEditMode() {
  // Show display sections
  // Hide edit form
  // Update button text
}
```

#### 2. Repeater Field Management

- **Add Items**: Dynamic addition of new form fields
- **Remove Items**: Deletion with automatic reindexing
- **Field Indexing**: Proper array indexing for form submission

#### 3. Smart Form Logic

- **Current Job Checkbox**: Automatically disables end date field
- **Form Validation**: Client-side validation for required fields
- **Skills Input**: Automatic comma separation and cleanup

#### 4. User Experience Enhancements

- **Success Messages**: Auto-hide after 5 seconds with fade effect
- **Smooth Animations**: CSS transitions for adding/removing items
- **Visual Feedback**: Hover effects and interactive states

---

## Data Structures

### Applicant Profile Meta Fields

#### Core Profile Data

```php
'_user_id' => int                    // Linked WordPress user ID
'_professional_title' => string     // Job title/position
'_phone' => string                  // Contact phone number
'_location' => string               // Geographic location
'_right_to_work' => string          // 'foreign' or 'australian'
'_work_types' => array              // Work preferences
'_available_for_work' => boolean    // Availability status
'_skills' => array                  // Skills list
'_linkedin_url' => string           // LinkedIn profile URL
```

#### Complex Profile Data

```php
'_education' => array               // Education history
'_experience' => array              // Work experience
'_licenses' => array                // Licenses & certifications
'_links' => array                   // Websites & social profiles
```

#### Personal Summary

- **Storage**: WordPress post content (`post_content`)
- **Display**: Rich text with `wpautop()` formatting
- **Editing**: Textarea with HTML support via `wp_kses_post()`

### Application Data Structure

```php
// Job Application CPT meta
'_job_id' => int                    // Related job listing ID
'_user_id' => int                   // Applicant user ID
'_applicant_id' => int              // Applicant CPT ID
'_app_status' => string             // Application status
'_application_date' => string       // Application submission date
'_resume_id' => int                 // Uploaded resume attachment ID
'_cover_letter' => text             // Cover letter content
```

---

## Asset Management

### CSS Architecture

#### 1. Applicant Dashboard Styles (`assets/css/applicant-dashboard.css`)

- **Container System**: Max-width layout with responsive padding
- **Grid Layout**: CSS Grid for dashboard sections and statistics
- **Component Styles**: Modular styling for cards, forms, buttons
- **Responsive Design**: Mobile-first approach with breakpoints
- **Form Styling**: Enhanced form elements with focus states
- **Animation System**: Smooth transitions and hover effects

#### 2. Key CSS Classes

```css
.cn-dashboard-container          // Main container
.cn-dashboard-header            // Header section with user info
.cn-dashboard-stats             // Statistics cards grid
.cn-dashboard-content           // Main content grid (2fr 1fr)
.cn-dashboard-section           // Individual content sections
.cn-application-card            // Application display cards
.cn-profile-edit-form           // Edit form container
.cn-repeater-item               // Repeater field items
.cn-form-section                // Form section containers;
```

### JavaScript Architecture

#### 1. Applicant Dashboard Script (`assets/js/applicant-dashboard.js`)

- **Edit Mode Management**: Toggle between view and edit modes
- **Repeater Field System**: Add/remove functionality for dynamic fields
- **Form Validation**: Client-side validation and user feedback
- **Smart Form Logic**: Current job checkbox behavior
- **User Experience**: Success message handling and input cleanup

#### 2. Key JavaScript Functions

```javascript
enterEditMode(); // Switch to edit mode
exitEditMode(); // Switch to view mode
initializeRepeaterFields(); // Setup repeater functionality
addRepeaterItem(); // Add new repeater item
initializeRemoveButtons(); // Setup remove functionality
reindexRepeaterItems(); // Reindex after removal
initializeCurrentJobCheckboxes(); // Current job logic
```

---

## Security Implementation

### Form Security

- **Nonce Verification**: All forms use WordPress nonces
- **Capability Checks**: User permissions verified before processing
- **Data Sanitization**: Comprehensive sanitization for all input types
- **File Upload Security**: Secure file handling with type/size validation

### Data Validation

```php
// Text fields
sanitize_text_field($input)

// Email addresses
sanitize_email($input)

// URLs
esc_url_raw($input)

// Rich text content
wp_kses_post($input)

// Textarea content
sanitize_textarea_field($input)

// Arrays
array_map('sanitize_text_field', $array)
```

### Access Control

- **Role-Based Access**: Applicant role required for dashboard access
- **Login Redirects**: Non-logged-in users redirected to login
- **Permission Checks**: `current_user_can()` checks throughout
- **Data Isolation**: Users can only access their own data

---

## Frontend Editing Experience

### User Journey

#### 1. View Mode (Default)

- Dashboard displays all profile sections with formatted data
- Statistics cards show application metrics
- Job recommendations based on profile
- Clean, professional layout optimized for viewing

#### 2. Edit Mode Activation

- Click "Edit Profile" button in header
- Display sections fade out and are replaced with edit forms
- Button text changes to "Cancel Edit"
- Form sections become visible with current data populated

#### 3. Form Interaction

- **Basic Information**: Standard form fields with validation
- **Repeater Sections**: Add/remove buttons for dynamic content
- **Smart Logic**: Current job checkbox disables end date
- **Visual Feedback**: Hover effects, focus states, validation messages

#### 4. Form Submission

- **Save Changes**: Processes form data and returns to view mode
- **Cancel**: Returns to view mode without saving changes
- **Success Feedback**: Success message with auto-hide functionality
- **Error Handling**: Validation errors displayed with clear messaging

### Responsive Design

#### Mobile Optimization

- **Header**: Stacked layout on mobile devices
- **Statistics**: 2-column grid on mobile, 4-column on desktop
- **Content**: Single column layout on mobile
- **Forms**: Full-width inputs with touch-friendly sizing
- **Buttons**: Appropriately sized for touch interaction

#### Breakpoints

```css
@media (max-width: 768px) {
  // Mobile-specific styles
  .cn-dashboard-content {
    grid-template-columns: 1fr;
  }
  .cn-dashboard-stats {
    grid-template-columns: repeat(2, 1fr);
  }
  .cn-header-content {
    flex-direction: column;
  }
}
```

---

## Data Persistence

### Storage Strategy

- **WordPress Integration**: Uses WordPress post meta for structured data
- **Array Storage**: Complex data stored as serialized arrays
- **Post Content**: Personal summary stored in post content for rich text support
- **User Meta**: Basic user information in WordPress user table

### Data Retrieval

```php
// Get applicant profile
$applicant_query = new WP_Query([
    'post_type' => 'applicant',
    'meta_query' => [
        ['key' => '_user_id', 'value' => $user_id, 'compare' => '=']
    ]
]);

// Get structured data
$education = get_post_meta($applicant_id, '_education', true);
$experience = get_post_meta($applicant_id, '_experience', true);
$licenses = get_post_meta($applicant_id, '_licenses', true);
$links = get_post_meta($applicant_id, '_links', true);
```

### Data Validation

- **Array Validation**: Ensures data is properly formatted as arrays
- **Empty Checks**: Validates required fields before processing
- **Type Checking**: Confirms data types match expectations
- **Sanitization**: All data sanitized before storage

---

## Performance Considerations

### Optimization Strategies

- **Conditional Asset Loading**: CSS/JS only loaded when needed
- **Efficient Queries**: Optimized database queries with proper indexing
- **Array Slicing**: Display limited items with "more" indicators
- **Lazy Loading**: Heavy sections loaded only when required

### Caching Strategy

- **WordPress Object Cache**: Leverages built-in caching where possible
- **Query Optimization**: Efficient meta queries with proper structure
- **Asset Optimization**: Minified and optimized CSS/JS files

---

## Extensibility

### Hook System

The system provides various hooks for customization:

#### Actions

```php
// Before/after form rendering
do_action('careernest_before_profile_form');
do_action('careernest_after_profile_form');

// Profile update events
do_action('careernest_profile_updated', $applicant_id, $user_id);
```

#### Filters

```php
// Template customization
apply_filters('careernest_template_path', $template_path);

// Form field customization
apply_filters('careernest_profile_fields', $fields);

// Data processing
apply_filters('careernest_profile_data', $data);
```

### Customization Points

- **Template Override**: Templates can be overridden in theme
- **CSS Customization**: Styles can be customized via theme CSS
- **Field Addition**: New profile fields can be added via hooks
- **Validation Rules**: Custom validation can be added via filters

---

## Browser Compatibility

### Supported Browsers

- **Modern Browsers**: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- **Mobile Browsers**: iOS Safari 14+, Chrome Mobile 90+
- **Progressive Enhancement**: Graceful degradation for older browsers

### JavaScript Features Used

- **ES6 Features**: Arrow functions, const/let, template literals
- **DOM APIs**: Modern DOM manipulation methods
- **CSS Features**: CSS Grid, Flexbox, CSS Custom Properties
- **Responsive Design**: CSS Media Queries, Viewport Meta Tag

---

## Testing & Quality Assurance

### Manual Testing Checklist

- [ ] Dashboard loads correctly for logged-in applicants
- [ ] Edit mode toggles properly between view and edit
- [ ] All form fields save and persist data correctly
- [ ] Repeater fields add/remove functionality works
- [ ] Current job checkbox disables end date field
- [ ] Form validation prevents invalid submissions
- [ ] Success/error messages display appropriately
- [ ] Public profile button opens in new tab
- [ ] Responsive design works on mobile devices
- [ ] Guest application system creates accounts and sends emails

### Security Testing

- [ ] Nonce verification prevents CSRF attacks
- [ ] Capability checks enforce proper permissions
- [ ] Data sanitization prevents XSS attacks
- [ ] File upload validation prevents malicious files
- [ ] SQL injection prevention through proper queries

### Performance Testing

- [ ] Page load times acceptable (<3 seconds)
- [ ] Asset loading optimized for page type
- [ ] Database queries efficient and indexed
- [ ] Mobile performance acceptable
- [ ] Large datasets handled gracefully

---

## Future Enhancements

### Planned Features

- **Resume Management**: Enhanced resume upload and management
- **Application Status Tracking**: Real-time status updates
- **Employer Dashboard**: Comprehensive employer management interface
- **Advanced Search**: Enhanced job search with filters
- **Notification System**: Real-time notifications for applications

### Technical Improvements

- **API Integration**: REST API endpoints for mobile apps
- **Advanced Caching**: Redis/Memcached integration
- **Search Enhancement**: Elasticsearch integration
- **File Management**: Advanced file handling and storage
- **Analytics**: Application and user behavior tracking

---

## Troubleshooting

### Common Issues

#### Edit Mode Not Working

- **Check**: JavaScript console for errors
- **Verify**: Button IDs and form elements exist
- **Solution**: Ensure assets are properly loaded

#### Form Data Not Saving

- **Check**: Nonce verification and user permissions
- **Verify**: Form field names match processing function
- **Solution**: Check PHP error logs for validation issues

#### Repeater Fields Not Functioning

- **Check**: JavaScript initialization and event binding
- **Verify**: Container IDs and button selectors
- **Solution**: Ensure proper DOM structure and CSS classes

#### Responsive Design Issues

- **Check**: CSS media queries and viewport meta tag
- **Verify**: Grid and flexbox browser support
- **Solution**: Test on actual devices and adjust breakpoints

### Debug Mode

Enable WordPress debug mode for detailed error reporting:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

---

## Conclusion

The CareerNest applicant dashboard represents a comprehensive, professional-grade profile management system that rivals commercial job platforms. The implementation follows WordPress best practices while providing modern UX patterns and extensive functionality for job seekers.

Key achievements:

- ✅ Complete template routing system
- ✅ Guest application functionality with automatic account creation
- ✅ Comprehensive applicant dashboard with statistics and tracking
- ✅ In-place editing system with professional UX
- ✅ Dynamic repeater fields for unlimited profile entries
- ✅ Responsive design with mobile optimization
- ✅ Secure data handling with proper validation and sanitization
- ✅ Extensible architecture for future enhancements

The system is ready for production use and provides a solid foundation for the remaining milestones in the CareerNest development roadmap.
