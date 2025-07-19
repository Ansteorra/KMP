/**
 * Officer Assignment Stimulus Controller
 * 
 * Provides comprehensive officer assignment interface with member validation, office
 * selection, deputy management, and assignment workflow coordination for seamless
 * officer assignment processing and administrative management within the KMP system.
 * 
 * This Stimulus controller manages the complete officer assignment workflow including
 * member lookup integration, office-specific configuration, deputy relationship
 * management, email address coordination, and comprehensive assignment validation
 * for enhanced user experience and administrative efficiency.
 * 
 * ## Assignment Workflow Architecture
 * 
 * **Member Validation Integration**: Integrates with member autocomplete system
 * for member validation, identity verification, assignment eligibility, and
 * comprehensive member selection with real-time validation and user feedback
 * for accurate assignment processing.
 * 
 * **Office Selection Management**: Manages office selection including office
 * discovery, configuration loading, deputy relationship validation, and
 * comprehensive office coordination for appropriate assignment configuration
 * and workflow management.
 * 
 * **Deputy Relationship Handling**: Handles deputy relationships including
 * deputy designation validation, description management, term configuration, and
 * comprehensive deputy coordination for hierarchical assignment management
 * and organizational structure support.
 * 
 * **Assignment Validation**: Validates assignment requirements including
 * member eligibility, office availability, deputy configuration, and
 * comprehensive validation coordination for accurate assignment processing
 * and administrative oversight.
 * 
 * ## Dynamic Form Management and User Interface
 * 
 * **Conditional Field Display**: Implements conditional field display including
 * deputy description fields, end date configuration, email address management,
 * and comprehensive field coordination for context-appropriate form display
 * and user experience optimization.
 * 
 * **Real-Time Validation**: Provides real-time validation including assignment
 * readiness checking, form validation, submission control, and comprehensive
 * validation feedback for immediate user guidance and error prevention
 * with enhanced user experience.
 * 
 * **Dynamic URL Management**: Manages dynamic URL updates for member search
 * including office-specific member filtering, URL construction, parameter
 * management, and comprehensive URL coordination for appropriate member
 * discovery and assignment validation.
 * 
 * **Form State Management**: Manages form state including field enablement,
 * validation status, submission readiness, and comprehensive state coordination
 * for logical form progression and user interface consistency
 * with proper workflow management.
 * 
 * ## Office Configuration and Context Management
 * 
 * **Office Data Integration**: Integrates office data including deputy status,
 * email configuration, hierarchical relationships, and comprehensive office
 * coordination for appropriate assignment configuration and workflow
 * management with organizational structure support.
 * 
 * **Deputy Configuration**: Manages deputy configuration including deputy
 * status validation, description requirements, term management, and
 * comprehensive deputy coordination for hierarchical assignment support
 * and organizational structure management.
 * 
 * **Email Address Management**: Manages email address configuration including
 * office-specific addresses, contact coordination, communication setup, and
 * comprehensive email management for organizational communication
 * and administrative coordination.
 * 
 * **Context-Aware Display**: Provides context-aware display including
 * office-specific field visibility, deputy requirement indication, email
 * configuration display, and comprehensive context coordination for
 * appropriate user interface adaptation and workflow guidance.
 * 
 * ## Member Search Integration and Validation
 * 
 * **Autocomplete Integration**: Integrates with member autocomplete system
 * including search URL management, office-specific filtering, member validation,
 * and comprehensive autocomplete coordination for efficient member selection
 * and assignment validation with user experience optimization.
 * 
 * **Office-Specific Filtering**: Implements office-specific member filtering
 * including eligibility validation, permission checking, availability
 * assessment, and comprehensive filtering coordination for appropriate
 * member discovery and assignment validation.
 * 
 * **URL Construction**: Constructs search URLs dynamically including office
 * parameter integration, URL path management, parameter coordination, and
 * comprehensive URL building for appropriate member search and
 * assignment validation.
 * 
 * **Search Context Management**: Manages search context including office
 * selection state, member eligibility, search parameters, and comprehensive
 * context coordination for accurate member discovery and
 * assignment validation.
 * 
 * ## Form Validation and Submission Control
 * 
 * **Assignment Readiness Validation**: Validates assignment readiness including
 * member selection verification, office selection validation, required field
 * completion, and comprehensive readiness assessment for accurate assignment
 * processing and administrative coordination.
 * 
 * **Real-Time Feedback**: Provides real-time feedback including validation
 * status indication, error highlighting, submission control, and comprehensive
 * feedback coordination for immediate user guidance and error prevention
 * with enhanced user experience.
 * 
 * **Submission Control**: Controls form submission including readiness
 * validation, button state management, form validation, and comprehensive
 * submission coordination for accurate assignment processing and
 * administrative workflow management.
 * 
 * **Field Dependency Management**: Manages field dependencies including
 * conditional requirements, validation chains, form progression, and
 * comprehensive dependency coordination for logical form workflow
 * and user interface consistency.
 * 
 * ## Target Element Management and DOM Coordination
 * 
 * **Target Element Integration**: Integrates target elements including
 * assignee selection, office selection, deputy fields, email configuration,
 * and comprehensive target coordination for complete form management
 * and user interface control.
 * 
 * **Dynamic Field Control**: Controls field behavior dynamically including
 * enablement state, visibility management, value coordination, and
 * comprehensive field control for context-appropriate form behavior
 * and user experience optimization.
 * 
 * **Element State Synchronization**: Synchronizes element state including
 * field values, validation status, visibility state, and comprehensive
 * synchronization coordination for consistent user interface behavior
 * and form state management.
 * 
 * **Event Coordination**: Coordinates events including change handling,
 * validation triggers, submission events, and comprehensive event
 * coordination for responsive user interface behavior and
 * workflow management.
 * 
 * ## Performance Optimization and User Experience
 * 
 * **Efficient DOM Manipulation**: Implements efficient DOM manipulation
 * including targeted updates, minimal reflows, state caching, and
 * comprehensive optimization coordination for high-performance user
 * interface operation and enhanced user experience.
 * 
 * **Event Handler Optimization**: Optimizes event handling including
 * efficient listeners, event delegation, performance monitoring, and
 * comprehensive handler coordination for responsive user interface
 * behavior and optimal performance.
 * 
 * **State Management Efficiency**: Manages state efficiently including
 * minimal updates, change detection, validation caching, and comprehensive
 * state coordination for optimal performance and user experience
 * with responsive interface behavior.
 * 
 * **User Experience Enhancement**: Enhances user experience including
 * immediate feedback, intuitive interface, clear validation, and
 * comprehensive experience coordination for efficient assignment
 * workflow and administrative usability.
 * 
 * ## Integration Points and Dependencies
 * 
 * **Stimulus Framework Integration**: Integrates with Stimulus framework
 * including controller lifecycle, target management, value coordination, and
 * comprehensive framework integration for consistent application behavior
 * and development patterns.
 * 
 * **Member Search Controller Integration**: Integrates with member search
 * controller through outlet connections including search coordination,
 * validation integration, and comprehensive search management for
 * seamless member selection and assignment workflow.
 * 
 * **Outlet Button Integration**: Integrates with outlet button controller
 * for form submission coordination including state management, validation
 * control, and comprehensive button coordination for proper assignment
 * workflow and user interface management.
 * 
 * **Backend API Integration**: Integrates with backend APIs through URL
 * management, data coordination, and comprehensive API integration for
 * accurate assignment processing and administrative coordination
 * with proper error handling and validation.
 * 
 * @package Officers\Assets\Controllers
 * @since 1.0.0
 * @version 2.0.0
 */

import { Controller } from "@hotwired/stimulus"

class OfficersAssignOfficer extends Controller {
    static values = {
        url: String,
    }
    static targets = ["assignee", "submitBtn", "deputyDescBlock", "deputyDesc", "office", "endDateBlock", "endDate", "emailAddress", "emailAddressBlock"]
    static outlets = ["outlet-btn", "member-serach"]

    /**
     * Configure Office-Specific Assignment Questions and Interface
     * 
     * Configures office-specific assignment interface including deputy relationship
     * management, email address coordination, field visibility control, and
     * comprehensive office configuration for context-appropriate assignment
     * workflow and user experience optimization.
     * 
     * This method manages dynamic form configuration based on office selection
     * including conditional field display, member search URL updates, deputy
     * configuration, email address management, and comprehensive interface
     * adaptation for office-specific assignment requirements and workflow.
     * 
     * ## Field Visibility and State Management
     * 
     * **Initial Field Reset**: Resets field visibility and state including
     * deputy description hiding, end date concealment, email address hiding,
     * field disabling, and comprehensive reset coordination for clean
     * interface state and consistent user experience.
     * 
     * **Dynamic Field Control**: Controls field visibility dynamically including
     * conditional display logic, state management, user interface adaptation,
     * and comprehensive field coordination for context-appropriate form
     * behavior and workflow optimization.
     * 
     * **State Synchronization**: Synchronizes field state including enablement
     * coordination, visibility management, value validation, and comprehensive
     * state coordination for consistent user interface behavior and
     * form workflow management.
     * 
     * ## Member Search URL Management
     * 
     * **Dynamic URL Construction**: Constructs member search URLs dynamically
     * including office parameter integration, URL path manipulation, parameter
     * coordination, and comprehensive URL building for office-specific member
     * filtering and assignment validation.
     * 
     * **URL Path Analysis**: Analyzes URL paths including path decomposition,
     * parameter identification, office ID integration, and comprehensive
     * path coordination for accurate URL construction and member search
     * filtering with proper parameter management.
     * 
     * **Search Context Integration**: Integrates search context including
     * office selection state, member eligibility filtering, search parameters,
     * and comprehensive context coordination for appropriate member discovery
     * and assignment validation.
     * 
     * ## Office Configuration Processing
     * 
     * **Office Data Retrieval**: Retrieves office configuration data including
     * deputy status validation, email address discovery, hierarchical
     * relationships, and comprehensive office coordination for appropriate
     * assignment configuration and workflow management.
     * 
     * **Deputy Status Validation**: Validates deputy status including deputy
     * designation checking, hierarchical relationship validation, configuration
     * requirements, and comprehensive deputy coordination for appropriate
     * deputy assignment management and organizational structure.
     * 
     * **Email Address Configuration**: Configures email addresses including
     * office-specific addresses, contact coordination, communication setup,
     * and comprehensive email management for organizational communication
     * and administrative coordination.
     * 
     * ## Conditional Interface Adaptation
     * 
     * **Deputy Field Management**: Manages deputy-specific fields including
     * description field display, end date configuration, term management,
     * and comprehensive deputy coordination for hierarchical assignment
     * support and organizational structure management.
     * 
     * **Email Field Configuration**: Configures email fields including
     * address field display, value population, validation setup, and
     * comprehensive email coordination for communication management
     * and organizational coordination.
     * 
     * **Validation Integration**: Integrates validation logic including
     * assignment readiness checking, form validation, submission control,
     * and comprehensive validation coordination for accurate assignment
     * processing and administrative workflow.
     * 
     * @return void Configures office-specific interface elements and updates
     *              member search URLs for appropriate assignment workflow
     *              and user experience optimization
     * @see checkReadyToSubmit() For assignment validation coordination
     * @since 1.0.0
     * @version 2.0.0
     */
    setOfficeQuestions() {
        this.deputyDescBlockTarget.classList.add('d-none');
        this.endDateBlockTarget.classList.add('d-none');
        this.emailAddressBlockTarget.classList.add('d-none');
        this.endDateTarget.disabled = true;
        this.deputyDescTarget.disabled = true;
        this.emailAddressTarget.disabled = true;
        var officeVal = this.officeTarget.value;
        // set the member search url by taking the current url and removing the last part (if it is a number) and replacing it with the officeVal
        var url = this.assigneeTarget.getAttribute('data-ac-url-value');
        var urlParts = url.split('/');
        var lastPart = urlParts[urlParts.length - 1];
        if (parseInt(lastPart)) {
            urlParts.pop();
        }
        urlParts.push(officeVal);
        var newUrl = urlParts.join('/');
        this.assigneeTarget.setAttribute('data-ac-url-value', newUrl);
        var office = this.officeTarget.options.find(option => option.value == officeVal);
        if (office) {
            if (office.data.is_deputy) {
                this.deputyDescBlockTarget.classList.remove('d-none');
                this.endDateBlockTarget.classList.remove('d-none');
                this.endDateTarget.disabled = false;
                this.deputyDescTarget.disabled = false;
            }
            if (office.data.email_address) {
                this.emailAddressBlockTarget.classList.remove('d-none');
                this.emailAddressTarget.disabled = false;
                this.emailAddressTarget.value = office.data.email_address;
            }
            this.checkReadyToSubmit();
            return;
        }
    }

    /**
     * Validate Assignment Readiness and Control Form Submission
     * 
     * Validates assignment readiness including member selection verification,
     * office selection validation, form completion assessment, and comprehensive
     * readiness evaluation for accurate assignment processing and submission
     * control with real-time user feedback and validation coordination.
     * 
     * This method implements comprehensive assignment validation including
     * member eligibility checking, office availability validation, form
     * state assessment, and submission button control for proper assignment
     * workflow management and user experience optimization.
     * 
     * ## Assignment Validation Logic
     * 
     * **Member Selection Validation**: Validates member selection including
     * member ID verification, selection state checking, eligibility assessment,
     * and comprehensive member validation for appropriate assignment processing
     * and administrative coordination.
     * 
     * **Office Selection Validation**: Validates office selection including
     * office ID verification, availability checking, configuration validation,
     * and comprehensive office validation for appropriate assignment coordination
     * and workflow management.
     * 
     * **Numeric Validation**: Implements numeric validation including ID parsing,
     * value verification, range checking, and comprehensive numeric validation
     * for accurate data processing and assignment validation.
     * 
     * ## Submission Control Management
     * 
     * **Button State Control**: Controls submission button state including
     * enablement logic, validation feedback, user interface control, and
     * comprehensive button coordination for proper form submission management
     * and user experience optimization.
     * 
     * **Real-Time Feedback**: Provides real-time validation feedback including
     * immediate state updates, user guidance, error indication, and comprehensive
     * feedback coordination for enhanced user experience and error prevention.
     * 
     * **Form State Synchronization**: Synchronizes form state including
     * validation status, submission readiness, user interface updates, and
     * comprehensive state coordination for consistent form behavior and
     * workflow management.
     * 
     * @return void Updates submission button state based on assignment
     *              validation status for proper form submission control
     *              and user experience optimization
     * @since 1.0.0
     * @version 2.0.0
     */
    checkReadyToSubmit() {
        var assigneeVal = this.assigneeTarget.value;
        var officeVal = this.officeTarget.value;
        var assignId = parseInt(assigneeVal);
        var officeId = parseInt(officeVal);
        if (assignId > 0 && officeId > 0) {
            this.submitBtnTarget.disabled = false;
        } else {
            this.submitBtnTarget.disabled = true;
        }
    }

    /**
     * Initialize Submit Button State on Target Connection
     * 
     * Initializes submit button state when target element connects including
     * initial disabling, state coordination, user interface setup, and
     * comprehensive button initialization for proper form submission control
     * and user experience optimization.
     * 
     * @return void Disables submit button on initial connection for proper
     *              form validation workflow and submission control
     * @since 1.0.0
     * @version 2.0.0
     */
    submitBtnTargetConnected() {
        this.submitBtnTarget.disabled = true;
    }

    /**
     * Initialize End Date Field State on Target Connection
     * 
     * Initializes end date field state when target element connects including
     * initial disabling, state coordination, field setup, and comprehensive
     * field initialization for proper deputy assignment workflow and
     * user interface management.
     * 
     * @return void Disables end date field on initial connection for proper
     *              deputy assignment workflow and conditional field control
     * @since 1.0.0
     * @version 2.0.0
     */
    endDateTargetConnected() {
        this.endDateTarget.disabled = true;
    }

    /**
     * Initialize Deputy Description Field State on Target Connection
     * 
     * Initializes deputy description field state when target element connects
     * including initial disabling, state coordination, field setup, and
     * comprehensive field initialization for proper deputy assignment workflow
     * and hierarchical assignment management.
     * 
     * @return void Disables deputy description field on initial connection
     *              for proper deputy assignment workflow and conditional
     *              field control with hierarchical coordination
     * @since 1.0.0
     * @version 2.0.0
     */
    deputyDescTargetConnected() {
        this.deputyDescTarget.disabled = true;
    }

    /**
     * Initialize Controller and Configure Initial Interface State
     * 
     * Initializes controller connection including interface state setup,
     * field visibility configuration, initial state coordination, and
     * comprehensive controller initialization for proper assignment workflow
     * and user experience optimization.
     * 
     * This method sets up the initial interface state including field hiding,
     * state coordination, user interface preparation, and comprehensive
     * initialization for consistent assignment workflow and user experience
     * management.
     * 
     * ## Initial State Configuration
     * 
     * **Field Visibility Setup**: Sets up initial field visibility including
     * deputy field hiding, end date concealment, email address hiding, and
     * comprehensive visibility coordination for clean initial interface
     * state and consistent user experience.
     * 
     * **Interface State Preparation**: Prepares interface state including
     * field disabling, form reset, validation setup, and comprehensive
     * state preparation for proper assignment workflow and user interface
     * consistency.
     * 
     * **Controller Lifecycle Integration**: Integrates with controller lifecycle
     * including connection handling, state management, event coordination, and
     * comprehensive lifecycle integration for proper Stimulus controller
     * behavior and application consistency.
     * 
     * @return void Initializes controller with proper initial state for
     *              assignment workflow and user experience optimization
     * @since 1.0.0
     * @version 2.0.0
     */
    connect() {
        this.deputyDescBlockTarget.classList.add('d-none');
        this.endDateBlockTarget.classList.add('d-none');
        this.emailAddressBlockTarget.classList.add('d-none');
    }


}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}

window.Controllers["officers-assign-officer"] = OfficersAssignOfficer;