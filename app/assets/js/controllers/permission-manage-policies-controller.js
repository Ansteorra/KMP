import { Controller } from "@hotwired/stimulus"

class PermissionManagePolicies extends Controller {
    static targets = ["policyClass", "policyMethod"]
    static values = {
        url: String,
    }

    changeQueue = []

    policyClassTargetConnected(element) {
        //add event listener to the element
        element.addEventListener("click", (event) => {
            this.classClicked(event)
        })

    }

    policyMethodTargetConnected(element) {
        //add event listener to the element
        element.addEventListener("click", (event) => {
            this.methodClicked(event)
        })
        this.checkClass(element);
    }

    checkClass(checkbox) {
        const isChecked = checkbox.checked
        const methodsList = checkbox.parentElement.parentElement.parentElement
        console.log(methodsList)
        const methods = methodsList.querySelectorAll("input[type='checkbox']")
        const allChecked = Array.from(methods).every((method) => method.checked)
        const classCheckbox = methodsList.parentElement.querySelector("input[type='checkbox']")
        classCheckbox.checked = allChecked
    }

    classClicked(event) {
        const checkbox = event.target
        const isChecked = checkbox.checked
        const methodsList = checkbox.parentElement.parentElement.querySelector("ul")
        const methods = methodsList.querySelectorAll("input[type='checkbox']")
        methods.forEach((method) => {
            method.checked = isChecked
            this.changeMethod(method, isChecked)
        })
    }
    methodClicked(event) {
        // check if the element is checked or not
        const checkbox = event.target
        const isChecked = checkbox.checked
        this.checkClass(checkbox);
        this.changeMethod(checkbox, isChecked)
    }
    changeMethod(method, isChecked) {
        // add to the change queue
        // get class name from the method
        //split the name of the input on -- with the first part being the class name and the second part being the method name
        const className = method.name.split("-")[0]
        // get the method name from the method
        // split the name of the input on -- with the second part being the method name
        const methodName = method.name.split("-")[1]
        this.changeQueue.push({
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
            element.removeEventListener("click", (event) => {
                this.classClicked(event)
            })
        })
        this.policyMethodTargets.forEach((element) => {
            element.removeEventListener("click", (event) => {
                this.methodClicked(event)
            })
        })
    }
}
if (!window.Controllers) {
    window.Controllers = {}
}
window.Controllers["permission-manage-policies"] = PermissionManagePolicies;