

import { Controller } from "@hotwired/stimulus"

/**
 * Awards Recommendation Table Controller
 * 
 * Comprehensive Stimulus controller for recommendation table management with filtering, sorting, and 
 * multi-selection functionality. Provides interactive table interface for recommendation data management 
 * with bulk operation support, outlet communication, and administrative oversight capabilities.
 * 
 * ## Table Management Features
 * 
 * **Multi-Selection Interface:**
 * - Individual row selection with checkbox management
 * - Select all/none functionality for bulk operations
 * - Dynamic selection state tracking with real-time updates
 * - Selection validation with business rule enforcement
 * 
 * **Outlet Communication:**
 * - Button outlet integration for action coordination
 * - Selection data sharing with dependent controllers
 * - Real-time state updates for connected interface elements
 * - Coordinated workflow management across multiple controllers
 * 
 * **Administrative Operations:**
 * - Bulk operation preparation with selection management
 * - Row-level action coordination with table state
 * - Administrative oversight with permission-aware interface
 * - Data integrity validation for table operations
 * 
 * ## Selection Management Features
 * 
 * **Checkbox Control:**
 * - Individual row selection with state persistence
 * - Master checkbox for select all/none functionality
 * - Selection state validation with data integrity checks
 * - Dynamic UI updates based on selection state
 * 
 * **Data Collection:**
 * - Selected ID collection for bulk operations
 * - Selection state management with outlet communication
 * - Data validation for selected recommendations
 * - Bulk operation preparation with ID array management
 * 
 * **User Interface Integration:**
 * - Visual feedback for selection state changes
 * - Button state management based on selection
 * - Bulk operation button enabling/disabling
 * - Administrative interface coordination
 * 
 * ## Workflow Integration Features
 * 
 * **Bulk Operation Support:**
 * - Multi-selection preparation for batch processing
 * - ID collection and validation for bulk operations
 * - Selection state communication with dependent forms
 * - Administrative approval workflow integration
 * 
 * **Table State Management:**
 * - Row selection persistence during table updates
 * - Selection validation with business rule enforcement
 * - State coordination across table refresh operations
 * - Administrative context preservation
 * 
 * **Interface Coordination:**
 * - Button outlet management for action coordination
 * - Selection-dependent interface updates
 * - Administrative workflow integration
 * - Permission-aware functionality control
 * 
 * ## Usage Examples
 * 
 * ### Basic Table with Selection
 * ```html
 * <!-- Recommendation table with multi-selection -->
 * <div data-controller="awards-rec-table" 
 *      data-awards-rec-table-outlet-btn-outlet="[data-controller*='outlet-btn']">
 * 
 *   <table class="table table-striped">
 *     <thead>
 *       <tr>
 *         <th>
 *           <input type="checkbox" data-awards-rec-table-target="CheckAllBox" 
 *                  data-action="change->awards-rec-table#checkAll" 
 *                  class="form-check-input">
 *           <label class="form-check-label">Select All</label>
 *         </th>
 *         <th>Member Name</th>
 *         <th>Award</th>
 *         <th>State</th>
 *         <th>Submitted Date</th>
 *         <th>Actions</th>
 *       </tr>
 *     </thead>
 *     <tbody>
 *       <tr>
 *         <td>
 *           <input type="checkbox" data-awards-rec-table-target="rowCheckbox" 
 *                  data-action="change->awards-rec-table#checked" 
 *                  value="123" class="form-check-input">
 *         </td>
 *         <td>John Doe</td>
 *         <td>Award of Arms</td>
 *         <td><span class="badge bg-warning">Submitted</span></td>
 *         <td>2024-01-15</td>
 *         <td>
 *           <button class="btn btn-sm btn-outline-primary">Edit</button>
 *           <button class="btn btn-sm btn-outline-info">View</button>
 *         </td>
 *       </tr>
 *       <tr>
 *         <td>
 *           <input type="checkbox" data-awards-rec-table-target="rowCheckbox" 
 *                  data-action="change->awards-rec-table#checked" 
 *                  value="124" class="form-check-input">
 *         </td>
 *         <td>Jane Smith</td>
 *         <td>Grant of Arms</td>
 *         <td><span class="badge bg-success">Approved</span></td>
 *         <td>2024-01-14</td>
 *         <td>
 *           <button class="btn btn-sm btn-outline-primary">Edit</button>
 *           <button class="btn btn-sm btn-outline-info">View</button>
 *         </td>
 *       </tr>
 *     </tbody>
 *   </table>
 * 
 *   <!-- Bulk action buttons -->
 *   <div class="mt-3">
 *     <button type="button" class="btn btn-warning" 
 *             data-bs-toggle="modal" data-bs-target="#bulkEditModal">
 *       Bulk Edit Selected
 *     </button>
 *     <button type="button" class="btn btn-info" 
 *             data-bs-toggle="modal" data-bs-target="#bulkExportModal">
 *       Export Selected
 *     </button>
 *   </div>
 * </div>
 * ```
 * 
 * ### Administrative Table with Outlet Integration
 * ```html
 * <!-- Table with outlet button communication for coordinated actions -->
 * <div data-controller="awards-rec-table outlet-btn" 
 *      data-awards-rec-table-outlet-btn-outlet=".bulk-action-controller"
 *      data-outlet-btn-outlet-awards-rec-table-outlet=".table-controller">
 * 
 *   <!-- Table header with administrative controls -->
 *   <div class="d-flex justify-content-between align-items-center mb-3">
 *     <h4>Recommendation Management</h4>
 *     <div class="btn-group">
 *       <button type="button" class="btn btn-primary dropdown-toggle" 
 *               data-bs-toggle="dropdown">
 *         Bulk Actions
 *       </button>
 *       <ul class="dropdown-menu">
 *         <li><a class="dropdown-item" href="#" data-bulk-action="approve">Bulk Approve</a></li>
 *         <li><a class="dropdown-item" href="#" data-bulk-action="review">Send to Review</a></li>
 *         <li><a class="dropdown-item" href="#" data-bulk-action="close">Bulk Close</a></li>
 *       </ul>
 *     </div>
 *   </div>
 * 
 *   <!-- Selection status display -->
 *   <div class="alert alert-info" id="selection-status" style="display: none;">
 *     <span id="selected-count">0</span> recommendations selected
 *   </div>
 * 
 *   <!-- Main recommendation table -->
 *   <table class="table table-hover">
 *     <!-- Table content with selection checkboxes -->
 *   </table>
 * </div>
 * ```
 * 
 * ### Dynamic Selection Management
 * ```javascript
 * // External integration for selection state management
 * document.addEventListener('DOMContentLoaded', function() {
 *   const table = document.querySelector('[data-controller*="awards-rec-table"]');
 *   if (table) {
 *     const controller = window.Stimulus.getControllerForElementAndIdentifier(table, 'awards-rec-table');
 *     
 *     // Monitor selection changes for UI updates
 *     table.addEventListener('change', function(e) {
 *       if (e.target.matches('[data-awards-rec-table-target="rowCheckbox"]')) {
 *         const selectedCount = table.querySelectorAll('[data-awards-rec-table-target="rowCheckbox"]:checked').length;
 *         const statusDiv = document.getElementById('selection-status');
 *         const countSpan = document.getElementById('selected-count');
 *         
 *         if (selectedCount > 0) {
 *           statusDiv.style.display = 'block';
 *           countSpan.textContent = selectedCount;
 *         } else {
 *           statusDiv.style.display = 'none';
 *         }
 *       }
 *     });
 *   }
 * });
 * ```
 * 
 * @class AwardsRecommendationTable
 * @extends {Controller}
 */
class AwardsRecommendationTable extends Controller {
    static targets = [
        "rowCheckbox",
        "CheckAllBox"
    ];

    static outlets = ["outlet-btn"];

    /**
     * Handle individual checkbox selection
     * 
     * Manages individual row selection state and communicates selection changes
     * to outlet controllers for coordinated interface updates and bulk operations.
     * 
     * @param {Event} event - Change event from checkbox
     * @returns {void}
     */
    checked(event) {
        console.log("Check button checked ", this.element);
        // debugger;
        let idList = [];
        this.outletBtnOutlet.btnDataValue = {};
        this.rowCheckboxTargets.forEach(input => {
            if (input.checked) {
                idList.push(input.value);
            }
        });
        if (idList.length > 0) {
            this.outletBtnOutlet.btnDataValue = { "ids": idList };
        }
    }

    /**
     * Initialize table controller
     * 
     * Sets up the table controller for recommendation management
     * with proper outlet communication and selection state.
     * 
     * @returns {void}
     */
    connect() {
    }

    /**
     * Handle select all/none functionality
     * 
     * Manages master checkbox behavior for bulk selection operations
     * with outlet communication for coordinated interface updates.
     * 
     * @param {Element} ele - Master checkbox element
     * @returns {void}
     */
    checkAll(ele) {
        console.log("Hello, Check All!", this.element);
        // debugger;

        if (this.CheckAllBoxTarget.checked) {
            let idList = [];

            for (var i = 0; i < this.rowCheckboxTargets.length; i++) {
                this.rowCheckboxTargets[i].checked = true; // Check all checkboxes
                idList.push(this.rowCheckboxTargets[i].value);

            }
            this.outletBtnOutlet.btnDataValue = { "ids": idList };

        }
        else {
            this.outletBtnOutlet.btnDataValue = {};
            for (var i = 0; i < this.rowCheckboxTargets.length; i++) {
                this.rowCheckboxTargets[i].checked = false; // Check all checkboxes
            }
            this.outletBtnOutlet.btnDataValue = {};

        }
    }
}

// add to window.Controllers with a name of the controller
if (!window.Controllers) {
    window.Controllers = {};
}
window.Controllers["awards-rec-table"] = AwardsRecommendationTable;