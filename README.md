# Custom Quiz System - Assignment-Based Assessment Platform

A comprehensive WordPress quiz plugin with Excel import, proportional question distribution, timed quiz with auto-submission, and detailed category-wise result reporting.

## Features

### Core Features
- **Excel/CSV Import**: Import questions from Excel files with automatic category mapping
- **Proportional Distribution**: Questions are distributed proportionally across categories based on available questions
- **2-Hour Timed Quiz**: Configurable time limit with auto-submission when time expires
- **User Assignment System**: Each user gets a unique assignment with their own set of questions
- **Progress Tracking**: Real-time progress tracking with auto-save functionality
- **Detailed Results**: Category-wise performance analysis with modern result display

### Technical Features
- **Modern UI**: Responsive design with real-time updates
- **AJAX Integration**: Smooth user experience with no page reloads
- **URL State Management**: Quiz state maintained through URL parameters
- **Database Optimization**: Efficient storage and retrieval of quiz data
- **Security**: Nonce verification and input sanitization

## Installation

1. Upload the plugin files to `/wp-content/plugins/custom-quiz/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Quiz System' > 'Settings' to configure the plugin
4. Use the shortcode `[quiz_assessment]` on any page or post

## Database Tables

The plugin creates the following database tables:

- `wp_cqz_assignments`: Stores user quiz assignments
- `wp_cqz_assignment_questions`: Stores questions assigned to each user
- `wp_cqz_results`: Stores detailed quiz results
- `wp_cqz_categories`: Stores quiz categories

## Settings Configuration

### General Settings
- **Time Limit**: Default quiz duration (default: 120 minutes)
- **Questions per Quiz**: Total questions per assessment (default: 40)
- **Proportional Distribution**: Enable proportional question distribution
- **Quiz Title**: Title displayed on welcome page
- **Welcome Message**: Custom welcome message

### Quiz Behavior
- **Allow Retakes**: Enable/disable quiz retakes
- **Randomize Questions**: Randomize question order
- **Randomize Choices**: Randomize answer choice order
- **Show Progress**: Display progress bar

### Results Settings
- **Show Results**: When to display results (immediate/delayed/never)
- **Show Correct Answers**: Display correct answers in results
- **Show Explanations**: Display question explanations
- **Save Results**: Store results for admin review
- **Email Results**: Send results to admin email

## Usage

### Shortcode
```
[quiz_assessment]
```

### URL Parameters
The system supports URL parameters for state management:
- `?action=quizstart&assignment_id=X`: Start quiz for specific assignment
- `?action=quiz_result&assignment_id=X`: View results for specific assignment

### Admin Interface
- **Dashboard**: Overview of questions, categories, and recent results
- **Settings**: Configure quiz behavior and appearance
- **Import**: Import questions from Excel/CSV files
- **Results**: View and manage quiz results

## File Structure

```
custom-quiz/
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   └── js/
│       ├── admin.js
│       └── frontend.js
├── includes/
│   ├── class-cqz-activator.php
│   ├── class-cqz-admin.php
│   ├── class-cqz-frontend.php
│   ├── class-cqz-import.php
│   ├── class-cqz-loader.php
│   ├── class-cqz-post-types.php
│   ├── class-cqz-results.php
│   ├── class-cqz-settings.php
│   ├── class-cqz-uninstaller.php
│   └── class-cqz-user-assignment.php
├── custom-quiz.php
├── README.md
└── readme.txt
```

## AJAX Endpoints

### User Assignment System
- `cqz_start_assessment`: Start a new quiz assessment
- `cqz_get_assignment`: Retrieve assignment data
- `cqz_save_answer`: Save individual answers
- `cqz_submit_quiz`: Submit completed quiz

## CSS Classes

### Quiz Interface
- `.cqz-assessment-landing`: Welcome page container
- `.cqz-quiz-container`: Main quiz container
- `.cqz-question`: Individual question styling
- `.cqz-timer`: Timer display
- `.cqz-progress`: Progress bar

### Results Display
- `.cqz-results-modern`: Modern results container
- `.cqz-results-header-modern`: Results header
- `.cqz-category-breakdown`: Category performance display

## JavaScript Functions

### Core Functions
- `initQuizInterface(assignmentId, timeLimit)`: Initialize quiz interface
- `updateQuizURL(action, assignmentId)`: Update URL parameters
- `saveAnswer(questionId, answer)`: Save individual answers
- `submitQuiz()`: Submit completed quiz

## Troubleshooting

### Common Issues
1. **Questions not loading**: Check if questions are imported and categories exist
2. **Timer not working**: Ensure JavaScript is properly loaded
3. **Results not showing**: Check AJAX response and database connectivity
4. **Import errors**: Verify Excel file format and column headers

### Debug Mode
Enable WordPress debug mode to see detailed error messages:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Support

For support and feature requests, please refer to the plugin documentation or contact the development team.

## Changelog

### Version 2.0
- Complete rewrite with assignment-based system
- Modern UI with real-time updates
- URL state management
- Enhanced result reporting
- Improved database structure

### Version 1.0
- Initial release with basic quiz functionality
- Excel import capability
- Basic result tracking 