import { Controller } from "@hotwired/stimulus"

class PermissionManagePolicies extends Controller {
    static targets = ["policyClass", "policyMethod"]
    static values = {
        url: String,
    }

    changeQueue = []

    policyClassTargetConnected(element) {
        //add event listener to the element
        element.clickEvent = (event) => {
            this.classClicked(event)
        }
        element.addEventListener("click", element.clickEvent)

    }

    policyMethodTargetConnected(element) {
        //add event listener to the element
        element.clickEvent = (event) => {
            this.methodClicked(event)
        }
        element.addEventListener("click", element.clickEvent)
    }

    connect() {
        // add event listener to all elements
        console.log("connected");
        const classes = document.querySelectorAll(`input[type='checkbox'][data-class-name][data-permission-id]:not([data-method-name])`);
        classes.forEach((element) => {
            const className = element.dataset.className
            const permissionId = element.dataset.permissionId
            this.checkClass(className, permissionId);
        });
    }

    checkClass(className, permissionId) {
        const methods = document.querySelectorAll(`input[type='checkbox'][data-class-name='${className}'][data-permission-id='${permissionId}'][data-method-name]`)
        let checkCount = 0
        methods.forEach((method) => {
            if (method.checked) {
                checkCount++
            }
        })
        const allChecked = checkCount === methods.length
        const someChecked = checkCount > 0 && checkCount < methods.length
        const classCheckbox = document.querySelectorAll(`input[type='checkbox'][data-class-name='${className}'][data-permission-id='${permissionId}']:not([data-method-name])`)[0]
        classCheckbox.checked = allChecked || someChecked
        if (someChecked) {
            // add the secondary class to the checkbox
            classCheckbox.classList.add("indeterminate-switch")
        } else {
            // remove the secondary class from the checkbox
            classCheckbox.classList.remove("indeterminate-switch")
        }

    }

    classClicked(event) {
        const checkbox = event.target
        const isChecked = checkbox.checked
        const className = checkbox.dataset.className
        const permissionId = checkbox.dataset.permissionId
        const methods = document.querySelectorAll(`input[type='checkbox'][data-class-name='${className}'][data-permission-id='${permissionId}'][data-method-name]`)
        methods.forEach((method) => {
            method.checked = isChecked
            this.changeMethod(method, isChecked)
        })
    }
    methodClicked(event) {
        // check if the element is checked or not
        const checkbox = event.target
        const isChecked = checkbox.checked
        const className = checkbox.dataset.className
        const permissionId = checkbox.dataset.permissionId
        this.checkClass(className, permissionId);
        this.changeMethod(checkbox, isChecked)
    }
    changeMethod(method, isChecked) {
        let className = method.dataset.className
        className = className.replace(/-/g, "\\");
        console.log(className);
        const methodName = method.dataset.methodName
        const permissionId = method.dataset.permissionId
        this.changeQueue.push({
            permissionId: permissionId,
            method: methodName,
            className: className,
            action: isChecked ? "add" : "delete",
        })
        // if the queue is empty then start the queue
        if (this.changeQueue.length === 1) {
            this.processQueue()
        }
    }
    processQueue() {
        if (this.changeQueue.length === 0) {
            return
        }
        const change = this.changeQueue[0]
        // make a fetch call to the controller url with the change
        fetch(this.urlValue, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "X-CSRF-Token": document.querySelector("meta[name='csrf-token']").content,
            },
            body: JSON.stringify(change),
        })
            .then((response) => response.json())
            .then((data) => {
                // remove the change from the queue
                this.changeQueue.shift()
                // process the next change in the queue
                this.processQueue()
            })
    }
    disconnect() {
        // remove event listeners from all elements
        this.policyClassTargets.forEach((element) => {
            element.removeEventListener("click", element.clickEvent);
        });
        this.policyMethodTargets.forEach((element) => {
            element.removeEventListener("click", element.clickEvent)
        });
    }
}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["permission-manage-policies"] = PermissionManagePolicies;