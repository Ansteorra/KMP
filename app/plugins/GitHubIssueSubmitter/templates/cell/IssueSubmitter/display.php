<?php

/**
 * GitHubIssueSubmitter Plugin - Issue Submission Form Template
 * 
 * This template provides a comprehensive user interface for anonymous feedback submission
 * to GitHub repositories through a Bootstrap modal dialog. It integrates with the 
 * GitHub API to create issues directly from user feedback.
 * 
 * ## Template Architecture
 * 
 * - **Bootstrap Modal Integration**: Uses Bootstrap 5 modal components for responsive UI
 * - **Stimulus Controller Integration**: Connected to github-submitter-controller for AJAX handling
 * - **Form Validation**: Client-side and server-side validation for data integrity
 * - **Responsive Design**: Mobile-friendly interface with accessibility support
 * - **Anonymous Submission**: No authentication required, privacy-focused design
 * 
 * ## UI Components
 * 
 * - **Trigger Button**: Small info button that opens the feedback modal
 * - **Modal Dialog**: Bootstrap modal with form fields and submission handling
 * - **Form Fields**: Title, feedback type selection, and detailed description
 * - **Success State**: Confirmation display with link to created GitHub issue
 * - **Error Handling**: User-friendly error messages for submission failures
 * 
 * ## Form Structure
 * 
 * - **Title Field**: Required text input for issue summary (GitHub issue title)
 * - **Feedback Type**: Dropdown selection (Feature Request, Bug, Other)
 * - **Details Field**: Textarea for comprehensive issue description (GitHub issue body)
 * - **Submit Button**: AJAX submission with loading state management
 * - **Close Button**: Modal dismissal without submission
 * 
 * ## Bootstrap Integration
 * 
 * - **Modal Helper**: Uses KMP Bootstrap plugin modal helper for consistent styling
 * - **Form Helper**: CakePHP form helper with Bootstrap classes for responsive forms
 * - **Button Classes**: Bootstrap button styling (btn-primary, btn-info, btn-sm)
 * - **Layout Classes**: Bootstrap grid and spacing utilities for responsive design
 * 
 * ## Accessibility Features
 * 
 * - **ARIA Labels**: Proper labeling for screen readers
 * - **Keyboard Navigation**: Full keyboard accessibility for form interaction
 * - **Focus Management**: Logical tab order and focus handling
 * - **Color Contrast**: Bootstrap classes ensure proper contrast ratios
 * - **Mobile Support**: Touch-friendly interface with appropriate sizing
 * 
 * ## Security Considerations
 * 
 * - **CSRF Protection**: CakePHP CSRF tokens automatically included in forms
 * - **Input Sanitization**: Server-side validation and sanitization of all inputs
 * - **Anonymous Submission**: No personal information collection or storage
 * - **Rate Limiting**: GitHub API rate limiting prevents abuse
 * 
 * ## Template Variables
 * 
 * @var bool $activeFeature Whether the plugin is active and should display
 * @var \Cake\View\View $this The view instance for helper access
 * 
 * ## Usage Examples
 * 
 * ### Basic Integration (Footer)
 * ```php
 * // In footer template
 * echo $this->cell('GitHubIssueSubmitter.IssueSubmitter::display', []);
 * ```
 * 
 * ### Custom Placement
 * ```php
 * // In any template with custom styling
 * <div class="feedback-section">
 *     <?= $this->cell('GitHubIssueSubmitter.IssueSubmitter::display', []) ?>
 * </div>
 * ```
 * 
 * ### Integration with Navigation
 * ```php
 * // In navigation menu
 * <li class="nav-item">
 *     <?= $this->cell('GitHubIssueSubmitter.IssueSubmitter::display', []) ?>
 * </li>
 * ```
 * 
 * ## Customization Examples
 * 
 * ### Custom Button Styling
 * ```php
 * // Override button classes in template
 * <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" 
 *         data-bs-target="#githubIssueModal" id='githubIssueModalBtn'>
 *     <i class="fas fa-bug"></i> Report Issue
 * </button>
 * ```
 * 
 * ### Additional Form Fields
 * ```php
 * // Add priority field
 * echo $this->Form->control("priority", [
 *     "label" => "Priority", 
 *     "type" => "select", 
 *     "options" => ["Low" => "Low", "Medium" => "Medium", "High" => "High"]
 * ]);
 * ```
 * 
 * ### Custom Modal Size
 * ```php
 * // Use larger modal for more content
 * echo $this->Modal->create("Submit Issue", [
 *     "id" => "githubIssueModal",
 *     "close" => true,
 *     "size" => "lg", // Large modal
 *     "data-github-submitter-target" => "modal",
 * ]);
 * ```
 * 
 * @package GitHubIssueSubmitter
 * @subpackage Template.Cell.IssueSubmitter
 * @since 1.0.0
 */

use App\KMP\StaticHelpers;

/**
 * Plugin Activation Check
 * 
 * Ensure the plugin is active before rendering any UI components.
 * This prevents rendering when the plugin is disabled via app settings.
 */
if (!$activeFeature) {
    return;
}

/**
 * Feedback Type Options Configuration
 * 
 * Define the available feedback categories that will be used as GitHub issue labels.
 * These options help categorize issues for better project management.
 * 
 * - Feature Request: New functionality suggestions
 * - Bug: Problem reports and error descriptions  
 * - Other: General feedback and uncategorized submissions
 */
$feedbackTypes = [
    "Feature Request" => "Feature Request",
    "Bug" => "Bug",
    "Other" => "Other",
];

/**
 * Stimulus Form Configuration
 * 
 * Configure the form element with Stimulus controller integration for AJAX processing.
 * The form connects to the github-submitter-controller for handling submission workflow.
 * 
 * Data attributes:
 * - data-controller: Registers the Stimulus controller
 * - data-github-submitter-target: Identifies the form for controller targeting
 * - data-github-submitter-url-value: API endpoint for AJAX submission
 */
echo $this->Form->create(null, [
    "data-controller" => "github-submitter",
    "data-github-submitter-target" => "form",
    "data-github-submitter-url-value" => $this->URL->build([
        "controller" => "Issues",
        "action" => "Submit",
        "plugin" => "GitHubIssueSubmitter"
    ]),
]);

/**
 * Bootstrap Modal Creation
 * 
 * Create a responsive Bootstrap modal dialog for the feedback submission form.
 * The modal provides a focused interface without navigating away from the current page.
 * 
 * Features:
 * - Responsive design that works on all screen sizes
 * - Close button for easy dismissal
 * - Accessibility support with proper ARIA attributes
 * - Integration with Stimulus controller for dynamic behavior
 */
echo $this->Modal->create("Submit Issue", [
    "id" => "githubIssueModal",
    "close" => true,
    "data-github-submitter-target" => "modal",
]); ?>

<!-- 
Form Block Container 
=====================
This container holds the main form interface and is managed by the Stimulus controller.
It will be hidden during success state display and shown during normal form interaction.
The controller uses this target to toggle between form and success states.
-->
<div data-github-submitter-target="formBlock">
    <fieldset class="text-start">
        <!-- User Guidance Message -->
        <div class="mb-3 text-wrap">
            <?= StaticHelpers::getAppSetting("Plugin.GitHubIssueSubmitter.PopupMessage") ?>
        </div>

        <?php
        /**
         * Issue Title Input Field
         * 
         * Primary field for the GitHub issue title. This becomes the issue headline
         * in the GitHub repository. Uses standard Bootstrap form styling for consistency.
         * 
         * Features:
         * - Required field (enforced client-side and server-side)
         * - Placeholder text for user guidance
         * - Responsive design with proper label association
         */
        echo $this->Form->control("title", [
            "label" => "Title",
            "placeholder" => "Enter a title for the issue."
        ]);

        /**
         * Feedback Type Selection
         * 
         * Dropdown selection for categorizing the feedback type. This helps with
         * issue organization and automated labeling in the GitHub repository.
         * 
         * Options:
         * - Feature Request: For new functionality suggestions
         * - Bug: For problem reports and error descriptions
         * - Other: For general feedback and uncategorized items
         */
        echo $this->Form->control("feedbackType", [
            "label" => "Feedback",
            "type" => "select",
            "options" => $feedbackTypes
        ]);

        /**
         * Detailed Description Textarea
         * 
         * Main content field for the GitHub issue body. Allows users to provide
         * comprehensive information about their feedback, bug reports, or feature requests.
         * 
         * Features:
         * - Large textarea for detailed descriptions
         * - Placeholder text with guidance
         * - Automatic resize based on content
         */
        echo $this->Form->control("body", [
            "label" => "Details",
            "type" => "textarea",
            "placeholder" => "Please provide a detailed description of the issue."
        ]);
        ?>
    </fieldset>
</div>

<!-- 
Success State Display 
====================
This container shows confirmation and success information after successful submission.
It's hidden by default and displayed by the Stimulus controller upon successful API response.
Includes a direct link to the created GitHub issue for user verification.
-->
<div data-github-submitter-target="success" class="text-center">
    <h3>Issue Submitted</h3>
    <p>Thank you for your feedback.</p>
    <a href="#" data-github-submitter-target="issueLink" target="_blank">
        View on Github
    </a>
</div>

<?php

/**
 * Modal Footer with Action Buttons
 * 
 * Configure the modal footer with primary actions for form submission and modal dismissal.
 * Uses Bootstrap button classes for consistent styling and proper user experience.
 * 
 * Submit Button Features:
 * - Bootstrap primary styling (blue)
 * - Stimulus target for controller access
 * - Click action bound to submission method
 * - Disabled state management during submission
 * 
 * Close Button Features:
 * - Bootstrap data attribute for modal dismissal
 * - No form submission or data processing
 * - Accessible keyboard navigation
 */
echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "data-github-submitter-target" => "submitBtn",
        "data-action" => "click->github-submitter#submit",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]);

/**
 * Form End and Cleanup
 * 
 * Properly close the CakePHP form element to ensure all form data and CSRF
 * tokens are correctly handled by the framework.
 */
echo $this->Form->end();

?>

<!-- 
Modal Trigger Button
===================
This is the primary user interface element that appears in the application layout.
Positioned in the footer, it provides an unobtrusive way for users to access
the feedback submission functionality.

Button Features:
- Small size (btn-sm) to minimize visual impact in footer
- Info color scheme (btn-info) for neutral, non-alarming appearance
- Bootstrap modal trigger attributes for proper modal activation
- Accessible text and keyboard navigation support
- Responsive design that works on all screen sizes

Placement Considerations:
- Located in footer to be available site-wide without cluttering main content
- Positioned after other footer links for logical tab order
- Uses nav-item structure for consistent footer styling
- Non-intrusive but discoverable for users seeking to provide feedback
-->
<button type="button" class="btn btn-info btn-sm"
    data-bs-toggle="modal"
    data-bs-target="#githubIssueModal"
    id='githubIssueModalBtn'>
    Submit Feedback
</button>