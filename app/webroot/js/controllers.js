(self["webpackChunk"] = self["webpackChunk"] || []).push([["/js/controllers"],{

/***/ "./assets/js/controllers/app-setting-form-controller.js":
/*!**************************************************************!*\
  !*** ./assets/js/controllers/app-setting-form-controller.js ***!
  \**************************************************************/
/***/ (function(__unused_webpack_module, __unused_webpack_exports, __webpack_require__) {

const {
  Controller
} = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");
class AppSettingForm extends Controller {
  static targets = ["submitBtn", "form"];
  submit(event) {
    event.preventDefault();
    this.formTarget.submit();
  }
  enableSubmit() {
    this.submitBtnTarget.disabled = false;
    this.submitBtnTarget.focus();
  }
}
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["app-setting-form"] = AppSettingForm;

/***/ }),

/***/ "./assets/js/controllers/auto-complete-controller.js":
/*!***********************************************************!*\
  !*** ./assets/js/controllers/auto-complete-controller.js ***!
  \***********************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");

const optionSelector = "[role='option']:not([aria-disabled])";
const activeSelector = "[aria-selected='true']";
class AutoComplete extends _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller {
  static targets = ["input", "hidden", "results", "dataList"];
  static classes = ["selected"];
  static values = {
    ready: Boolean,
    submitOnEnter: Boolean,
    url: String,
    minLength: Number,
    allowOther: Boolean,
    required: Boolean,
    delay: {
      type: Number,
      default: 300
    },
    queryParam: {
      type: String,
      default: "q"
    }
  };
  static uniqOptionId = 0;
  initialize() {
    this._selectOptions = [];
  }

  // Getter for the value property
  get value() {
    // if there is a hidden value return that
    if (this.hasHiddenTarget.value != "") {
      return this.hiddenTarget.value;
    } else {
      //if we allow other values return the input value
      if (this.allowOtherValue) {
        return this.inputTarget.value;
      }
      return "";
    }
  }

  // Setter for the value property
  set value(newValue) {
    //check if the new value is an object with a "value" property and "text" property
    if (typeof newValue === "object" && newValue.hasOwnProperty("value") && newValue.hasOwnProperty("text")) {
      this.inputTarget.value = newValue.text;
      this.hiddenTarget.value = newValue.value;
      return;
    }
    //if the value matches an option set the input value to the option text
    if (newValue != "" && newValue != null) {
      let option = this._selectOptions.find(option => option.value == newValue);
      if (!option) {
        if (this.hasDataListTarget) {
          var newOptions = this.options;
          newOptions.push({
            value: newValue,
            text: newValue
          });
          this.options = newOptions;
        }
      }
      if (option) {
        this.inputTarget.value = option.text;
        this.hiddenTarget.value = option.value;
        return;
      } else {
        if (this.allowOtherValue) {
          this.inputTarget.value = newValue;
          this.hiddenTarget.value = newValue;
        } else {
          this.inputTarget.value = "";
          this.hiddenTarget.value = "";
        }
        return;
      }
    }
    this.inputTarget.value = "";
    this.hiddenTarget.value = "";
  }
  get disabled() {
    return this.inputTarget.disabled;
  }
  set disabled(newValue) {
    this.inputTarget.disabled = newValue;
    this.hiddenTarget.disabled = newValue;
  }
  get hidden() {
    return this.element.hidden;
  }
  set hidden(newValue) {
    this.element.hidden = newValue;
  }
  get options() {
    return this._selectOptions;
  }
  set options(newValue) {
    this._selectOptions = newValue;
    this.makeDataListItems();
  }
  makeDataListItems() {
    if (this.hasDataListTarget) {
      this.dataListTarget.textContent = "";
      var items = JSON.stringify(this._selectOptions);
      this.dataListTarget.textContent = items;
    }
  }
  dataListTargetConnected() {
    console.log("DataList Target Connected");
    this._selectOptions = JSON.parse(this.dataListTarget.textContent);
  }
  connect() {
    this.state = "start";
    this.close();
    this.state = "ready";
    if (!this.inputTarget.hasAttribute("autocomplete")) this.inputTarget.setAttribute("autocomplete", "off");
    this.inputTarget.setAttribute("spellcheck", "false");
    this.mouseDown = false;
    this.onInputChange = debounce(this.onInputChange, this.delayValue);
    this.inputTarget.addEventListener("keydown", this.onKeydown);
    this.inputTarget.addEventListener("blur", this.onInputBlur);
    this.inputTarget.addEventListener("input", this.onInputChange);
    this.inputTarget.addEventListener("click", this.onInputClick);
    this.inputTarget.addEventListener("change", this.onInputChangeTriggered);
    this.resultsTarget.addEventListener("mousedown", this.onResultsMouseDown);
    this.resultsTarget.addEventListener("click", this.onResultsClick);
    if (this.inputTarget.hasAttribute("autofocus")) {
      this.inputTarget.focus();
    }
    this.shimElement();
    this.readyValue = true;
    this.element.dispatchEvent(new CustomEvent("ready", {
      detail: this.element.dataset
    }));
  }
  shimElement() {
    Object.defineProperty(this.element, 'value', {
      get: () => {
        return this.value;
      },
      set: newValue => {
        this.value = newValue;
      }
    });
    this.element.focus = () => {
      this.inputTarget.focus();
    };
    let proto = this.element;
    while (proto && !Object.getOwnPropertyDescriptor(proto, 'hidden')) {
      proto = Object.getPrototypeOf(proto);
    }
    if (proto) {
      this.baseHidden = Object.getOwnPropertyDescriptor(proto, 'hidden');
      Object.defineProperty(this.element, 'hidden', {
        get: () => {
          return this.baseHidden.get.call(this.element);
        },
        set: newValue => {
          this.baseHidden.set.call(this.element, newValue);
          if (newValue) {
            this.hiddenTarget.disabled = true;
            this.inputTarget.disabled = true;
            this.close();
          } else {
            this.hiddenTarget.disabled = false;
            this.inputTarget.disabled = false;
          }
        }
      });
    }
    Object.defineProperty(this.element, 'disabled', {
      get: () => {
        return this.disabled;
      },
      set: newValue => {
        this.disabled = newValue;
      }
    });
    Object.defineProperty(this.element, 'options', {
      get: () => {
        return this.options;
      },
      set: newValue => {
        this.options = newValue;
      }
    });
  }
  disconnect() {
    if (this.hasInputTarget) {
      this.inputTarget.removeEventListener("keydown", this.onKeydown);
      this.inputTarget.removeEventListener("blur", this.onInputBlur);
      this.inputTarget.removeEventListener("input", this.onInputChange);
      this.inputTarget.removeEventListener("click", this.onInputClick);
      this.inputTarget.removeEventListener("change", this.onInputChangeTriggered);
    }
    if (this.hasResultsTarget) {
      this.resultsTarget.removeEventListener("mousedown", this.onResultsMouseDown);
      this.resultsTarget.removeEventListener("click", this.onResultsClick);
    }
  }
  sibling(next) {
    const options = this.options;
    const selected = this.selectedOption;
    const index = options.indexOf(selected);
    const sibling = next ? options[index + 1] : options[index - 1];
    const def = next ? options[0] : options[options.length - 1];
    return sibling || def;
  }
  select(target) {
    const previouslySelected = this.selectedOption;
    if (previouslySelected) {
      previouslySelected.removeAttribute("aria-selected");
      previouslySelected.classList.remove(...this.selectedClassesOrDefault);
    }
    target.setAttribute("aria-selected", "true");
    target.classList.add(...this.selectedClassesOrDefault);
    this.inputTarget.setAttribute("aria-activedescendant", target.id);
    target.scrollIntoView({
      behavior: "auto",
      block: "nearest"
    });
  }
  onInputChangeTriggered = event => {
    event.stopPropagation();
  };
  onInputClick = event => {
    if (this.hasDataListTarget) {
      const query = this.inputTarget.value.trim();
      this.fetchResults(query);
    }
  };
  onKeydown = event => {
    const handler = this[`on${event.key}Keydown`];
    if (handler) handler(event);
  };
  onEscapeKeydown = event => {
    if (!this.resultsShown) return;
    this.hideAndRemoveOptions();
    event.stopPropagation();
    event.preventDefault();
  };
  onArrowDownKeydown = event => {
    const item = this.sibling(true);
    if (item) this.select(item);
    event.preventDefault();
  };
  onArrowUpKeydown = event => {
    const item = this.sibling(false);
    if (item) this.select(item);
    event.preventDefault();
  };
  onTabKeydown = event => {
    const selected = this.selectedOption;
    if (selected) this.commit(selected);
  };
  onEnterKeydown = event => {
    const selected = this.selectedOption;
    if (selected && this.resultsShown) {
      this.commit(selected);
      if (!this.hasSubmitOnEnterValue) {
        event.preventDefault();
      }
    }
  };
  onInputBlur = () => {
    if (this.mouseDown) return;
    if (this.state !== "finished" && this.state !== "start") {
      if (this.allowOtherValue) {
        this.fireChangeEvent(this.inputTarget.value, this.inputTarget.value, null);
      } else {
        this.clear();
      }
    }
    this.close();
  };
  commit(selected) {
    if (selected.getAttribute("aria-disabled") === "true") return;
    if (selected instanceof HTMLAnchorElement) {
      selected.click();
      this.close();
      return;
    }
    const textValue = selected.getAttribute("data-ac-label") || selected.textContent.trim();
    const value = selected.getAttribute("data-ac-value") || textValue;
    this.inputTarget.value = textValue;
    if (this.hasHiddenTarget) {
      this.hiddenTarget.value = value;
      this.hiddenTarget.dispatchEvent(new Event("input"));
      this.hiddenTarget.dispatchEvent(new Event("change"));
    } else {
      this.inputTarget.value = value;
    }
    this.inputTarget.focus();
    this.state = "finished";
    this.fireChangeEvent(value, textValue, selected);
    this.hideAndRemoveOptions();
  }
  fireChangeEvent(value, textValue, selected) {
    this.element.dispatchEvent(new CustomEvent("autocomplete.change", {
      bubbles: true,
      detail: {
        value: value,
        textValue: textValue,
        selected: selected
      }
    }));
    this.element.dispatchEvent(new CustomEvent("change"), {
      bubbles: true
    });
  }
  clear() {
    this.inputTarget.value = "";
    if (this.hasHiddenTarget) this.hiddenTarget.value = "";
  }
  onResultsClick = event => {
    if (!(event.target instanceof Element)) return;
    const selected = event.target.closest(optionSelector);
    if (selected) this.commit(selected);
  };
  onResultsMouseDown = () => {
    this.mouseDown = true;
    this.resultsTarget.addEventListener("mouseup", () => {
      this.mouseDown = false;
    }, {
      once: true
    });
  };
  onInputChange = () => {
    if (this.hasHiddenTarget) this.hiddenTarget.value = "";
    const query = this.inputTarget.value.trim();
    if (query && query.length >= this.minLengthValue || this.hasDataListTarget) {
      this.fetchResults(query);
    } else {
      this.hideAndRemoveOptions();
    }
  };
  identifyOptions() {
    const prefix = this.resultsTarget.id || "stimulus-autocomplete";
    const optionsWithoutId = this.resultsTarget.querySelectorAll(`${optionSelector}:not([id])`);
    optionsWithoutId.forEach(el => el.id = `${prefix}-option-${AutoComplete.uniqOptionId++}`);
  }
  hideAndRemoveOptions() {
    this.close();
    this.resultsTarget.innerHTML = null;
  }
  fetchResults = async query => {
    if (!this.hasUrlValue) {
      if (!this.hasDataListTarget) {
        throw new Error("You must provide a URL or a DataList target");
      } else {
        this.resultsTarget.innerHTML = null;
        let allItems = this._selectOptions;
        for (let item of allItems) {
          if (item.text.toLowerCase().includes(query.toLowerCase())) {
            let itemHtml = document.createElement("li");
            itemHtml.setAttribute("data-ac-value", item.value);
            itemHtml.classList.add("list-group-item");
            itemHtml.setAttribute("role", "option");
            itemHtml.setAttribute("aria-selected", "false");
            itemHtml.textContent = item.text;
            //add a span around matching string to highlight it
            itemHtml.innerHTML = itemHtml.innerHTML.replace(new RegExp(query, 'gi'), match => `<span class="text-primary">${match}</span>`);
            this.resultsTarget.appendChild(itemHtml);
          }
        }
        this.identifyOptions();
        this.open();
        return;
      }
    }
    const url = this.buildURL(query);
    try {
      this.element.dispatchEvent(new CustomEvent("loadstart"));
      const html = await this.doFetch(url);
      this.replaceResults(html);
      this.element.dispatchEvent(new CustomEvent("load"));
      this.element.dispatchEvent(new CustomEvent("loadend"));
    } catch (error) {
      this.element.dispatchEvent(new CustomEvent("error"));
      this.element.dispatchEvent(new CustomEvent("loadend"));
      throw error;
    }
  };
  buildURL(query) {
    const url = new URL(this.urlValue, window.location.href);
    const params = new URLSearchParams(url.search.slice(1));
    params.append(this.queryParamValue, query);
    url.search = params.toString();
    return url.toString();
  }
  doFetch = async url => {
    const response = await fetch(url, this.optionsForFetch());
    if (!response.ok) {
      throw new Error(`Server responded with status ${response.status}`);
    }
    const html = await response.text();
    return html;
  };
  replaceResults(html) {
    this.resultsTarget.innerHTML = html;
    this.identifyOptions();
    if (!!this.options) {
      this.state = "results";
      this.open();
    } else {
      this.state = "empty list";
      this.close();
    }
  }
  open() {
    if (this.resultsShown) return;
    this.resultsShown = true;
    this.element.setAttribute("aria-expanded", "true");
    this.element.dispatchEvent(new CustomEvent("toggle", {
      detail: {
        action: "open",
        inputTarget: this.inputTarget,
        resultsTarget: this.resultsTarget
      }
    }));
  }
  close() {
    if (!this.resultsShown) {
      return;
    }
    this.resultsShown = false;
    this.inputTarget.removeAttribute("aria-activedescendant");
    this.element.setAttribute("aria-expanded", "false");
    this.element.dispatchEvent(new CustomEvent("toggle", {
      detail: {
        action: "close",
        inputTarget: this.inputTarget,
        resultsTarget: this.resultsTarget
      }
    }));
  }
  get resultsShown() {
    return !this.resultsTarget.hidden;
  }
  set resultsShown(value) {
    this.resultsTarget.hidden = !value;
  }
  get selectedClassesOrDefault() {
    return this.hasSelectedClass ? this.selectedClasses : ["active"];
  }
  optionsForFetch() {
    return {
      headers: {
        "X-Requested-With": "XMLHttpRequest"
      }
    }; // override if you need
  }
}
const debounce = function (fn) {
  let delay = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 10;
  let timeoutId = null;
  return function () {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(fn, delay);
  };
};
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["ac"] = AutoComplete;

/***/ }),

/***/ "./assets/js/controllers/detail-tabs-controller.js":
/*!*********************************************************!*\
  !*** ./assets/js/controllers/detail-tabs-controller.js ***!
  \*********************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");

class DetailTabsController extends _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller {
  static targets = ["tabBtn", "tabContent"];
  foundFirst = false;
  tabBtnTargetConnected(event) {
    var tab = event.id.replace('nav-', '').replace('-tab', '');
    var urlTab = KMP_utils.urlParam('tab');
    if (urlTab) {
      if (tab == urlTab) {
        event.click();
        this.foundFirst = true;
        window.scrollTo(0, 0);
      }
    } else {
      if (!this.foundFirst) {
        this.tabBtnTargets[0].click();
        window.scrollTo(0, 0);
      }
    }
    event.addEventListener('click', this.tabBtnClicked.bind(this));
  }
  tabBtnClicked(event) {
    var firstTabId = this.tabBtnTargets[0].id;
    var eventTabId = event.target.id;
    if (firstTabId != eventTabId) {
      var tab = event.target.id.replace('nav-', '').replace('-tab', '');
      window.history.pushState({}, '', '?tab=' + tab);
    } else {
      //only push state if there is a tab in the querystring
      var urlTab = KMP_utils.urlParam('tab');
      if (urlTab) {
        window.history.pushState({}, '', window.location.pathname);
      }
    }
  }
  tabBtnTargetDisconnected(event) {
    event.removeEventListener('click', this.tabBtnClicked.bind(this));
  }
}
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["detail-tabs"] = DetailTabsController;

/***/ }),

/***/ "./assets/js/controllers/grid-button-controller.js":
/*!*********************************************************!*\
  !*** ./assets/js/controllers/grid-button-controller.js ***!
  \*********************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");

class GridButton extends _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller {
  static values = {
    rowData: Object
  };
  fireNotice(event) {
    let rowData = this.rowDataValue;
    this.dispatch("grid-button-clicked", {
      detail: rowData
    });
  }
  addListener(callback) {
    this.element.addEventListener("grid-btn:grid-button-clicked", callback);
  }
  removeListener(callback) {
    this.element.removeEventListener("grid-btn:grid-button-clicked", callback);
  }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["grid-btn"] = GridButton;

/***/ }),

/***/ "./assets/js/controllers/image-preview-controller.js":
/*!***********************************************************!*\
  !*** ./assets/js/controllers/image-preview-controller.js ***!
  \***********************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");

class ImagePreview extends _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller {
  static targets = ['file', 'preview', 'loading'];
  preview(event) {
    if (event.target.files.length > 0) {
      let src = URL.createObjectURL(event.target.files[0]);
      this.previewTarget.src = src;
      this.loadingTarget.classList.add("d-none");
      this.previewTarget.hidden = false;
    }
  }
}
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["image-preview"] = ImagePreview;

/***/ }),

/***/ "./assets/js/controllers/member-card-profile-controller.js":
/*!*****************************************************************!*\
  !*** ./assets/js/controllers/member-card-profile-controller.js ***!
  \*****************************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");

class MemberCardProfile extends _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller {
  static targets = ["cardSet", "firstCard", "name", "scaName", "branchName", "membershipInfo", "backgroundCheck", "lastUpdate", "loading", "memberDetails"];
  static values = {
    url: String
  };
  initialize() {
    this.currentCard = null;
    this.cardCount = 1;
    this.maxCardLength = 0;
  }
  usedSpaceInCard() {
    var cardChildren = this.currentCard.children;
    var runningTotal = 0;
    for (var i = 0; i < cardChildren.length; i++) {
      runningTotal += cardChildren[i].offsetHeight;
    }
    return runningTotal;
  }
  appendToCard(element, minSpace) {
    this.currentCard.appendChild(element);
    if (minSpace === null) {
      minSpace = 2;
    }
    minSpace = this.maxCardLength * (minSpace / 100);
    if (this.usedSpaceInCard() > this.maxCardLength - minSpace) {
      this.currentCard.removeChild(element);
      this.startCard();
      this.currentCard.appendChild(element);
    }
  }
  startCard() {
    this.cardCount++;
    var card = document.createElement("div");
    card.classList.add("auth_card");
    card.id = "card_" + this.cardCount;
    var cardDetails = document.createElement("div");
    cardDetails.classList.add("cardbox");
    cardDetails.id = "cardDetails_" + this.cardCount;
    card.appendChild(cardDetails);
    this.cardSetTarget.appendChild(card);
    this.currentCard = cardDetails;
  }
  loadCard() {
    this.currentCard = this.firstCardTarget;
    this.maxCardLength = this.firstCardTarget.offsetHeight;
    this.cardCount = 1;
    fetch(this.urlValue).then(response => response.json()).then(data => {
      this.nameTarget.textContent = data.member.first_name + ' ' + data.member.last_name;
      this.scaNameTarget.textContent = data.member.sca_name;
      this.branchNameTarget.textContent = data.member.branch.name;
      if (data.member.membership_number && data.member.membership_number.length > 0) {
        var memberExpDate = new Date(data.member.membership_expires_on);
        if (memberExpDate < new Date()) {
          memberExpDate = "Expired";
        } else {
          memberExpDate = " - " + memberExpDate.toLocaleDateString();
        }
        this.membershipInfoTarget.textContent = data.member.membership_number + ' ' + memberExpDate;
      } else {
        this.membershipInfoTarget.innerHtml = "";
        this.membershipInfoTarget.textContent = "No Membership Info";
      }
      if (data.member.background_check_expires_on) {
        var backgroundCheckExpDate = new Date(data.member.background_check_expires_on);
        if (backgroundCheckExpDate < new Date()) {
          backgroundCheckExpDate = "Expired";
        } else {
          backgroundCheckExpDate = " - " + backgroundCheckExpDate.toLocaleDateString();
        }
        strong = document.createElement("strong");
        strong.textContent = backgroundCheckExpDate;
        this.backgroundCheckTarget.innerHtml = "";
        this.backgroundCheckTarget.appendChild(strong);
      } else {
        this.backgroundCheckTarget.innerHtml = "";
        this.backgroundCheckTarget.textContent = "No Background Check";
      }
      var today = new Date();
      this.lastUpdateTarget.textContent = today.toLocaleDateString();
      this.loadingTarget.hidden = true;
      this.memberDetailsTarget.hidden = false;
      for (let key in data) {
        if (key === 'member') {
          continue;
        }
        var pluginData = data[key];
        for (let sectionKey in pluginData) {
          var sectionData = pluginData[sectionKey];
          var groupCount = sectionData.length;
          if (groupCount === 0) {
            continue;
          }
          var sectionHeader = document.createElement("h3");
          sectionHeader.textContent = sectionKey;
          this.appendToCard(sectionHeader, 20);
          for (let groupKey in sectionData) {
            var groupData = sectionData[groupKey];
            var groupHeader = document.createElement("h5");
            groupHeader.textContent = groupKey;
            var groupDiv = document.createElement("div");
            groupDiv.classList.add("cardGroup");
            groupDiv.appendChild(groupHeader);
            var groupList = document.createElement("ul");
            for (let i = 0; i < groupData.length; i++) {
              var itemValue = groupData[i];
              var listItem = document.createElement("li");
              listItem.textContent = itemValue;
              groupList.appendChild(listItem);
            }
            groupDiv.appendChild(groupList);
            this.appendToCard(groupDiv, 10);
          }
        }
      }
    });
  }
  connect() {
    this.loadCard();
  }
}
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["member-card-profile"] = MemberCardProfile;

/***/ }),

/***/ "./assets/js/controllers/member-mobile-card-profile-controller.js":
/*!************************************************************************!*\
  !*** ./assets/js/controllers/member-mobile-card-profile-controller.js ***!
  \************************************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");

class MemberMobileCardProfile extends _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller {
  static targets = ["cardSet", "name", "scaName", "branchName", "membershipInfo", "backgroundCheck", "lastUpdate", "loading", "memberDetails"];
  static values = {
    url: String
  };
  initialize() {
    this.currentCard = null;
    this.cardCount = 0;
  }
  startCard(title) {
    this.cardCount++;
    var card = document.createElement("div");
    card.classList.add("card", "cardbox", "m-3");
    card.id = "card_" + this.cardCount;
    var cardDetails = document.createElement("div");
    cardDetails.classList.add("card-body");
    cardDetails.id = "cardDetails_" + this.cardCount;
    var cardTitle = document.createElement("h3");
    cardTitle.classList.add("card-title", "text-center", "display-6");
    cardTitle.textContent = title;
    cardDetails.appendChild(cardTitle);
    card.appendChild(cardDetails);
    this.cardSetTarget.appendChild(card);
    this.currentCard = cardDetails;
  }
  loadCard() {
    this.cardSetTarget.innerHTML = "";
    this.loadingTarget.hidden = false;
    this.memberDetailsTarget.hidden = true;
    fetch(this.urlValue).then(response => response.json()).then(data => {
      this.loadingTarget.hidden = true;
      this.memberDetailsTarget.hidden = false;
      this.nameTarget.textContent = data.member.first_name + ' ' + data.member.last_name;
      this.scaNameTarget.textContent = data.member.sca_name;
      this.branchNameTarget.textContent = data.member.branch.name;
      if (data.member.membership_number && data.member.membership_number.length > 0) {
        var memberExpDate = new Date(data.member.membership_expires_on);
        if (memberExpDate < new Date()) {
          memberExpDate = "Expired";
        } else {
          memberExpDate = " - " + memberExpDate.toLocaleDateString();
        }
        this.membershipInfoTarget.textContent = data.member.membership_number + ' ' + memberExpDate;
      } else {
        this.membershipInfoTarget.textContent = "No Membership Info";
      }
      if (data.member.background_check_expires_on) {
        var backgroundCheckExpDate = new Date(data.member.background_check_expires_on);
        if (backgroundCheckExpDate < new Date()) {
          backgroundCheckExpDate = "Expired";
        } else {
          backgroundCheckExpDate = 'Current' + backgroundCheckExpDate.toLocaleDateString();
        }
        this.backgroundCheckTarget.textContent = backgroundCheckExpDate;
      } else {
        this.backgroundCheckTarget.textContent = "Not on file";
      }
      this.lastUpdateTarget.textContent = new Date().toLocaleString();
      for (let key in data) {
        if (key === 'member') {
          continue;
        }
        var pluginData = data[key];
        for (let sectionKey in pluginData) {
          var sectionData = pluginData[sectionKey];
          var keysCount = Object.keys(sectionData).length;
          if (keysCount > 0) {
            this.startCard(sectionKey);
          } else {
            continue;
          }
          var groupTable = document.createElement("table");
          groupTable.classList.add("table", "card-body-table");
          var groupTableBody = document.createElement("tbody");
          groupTable.appendChild(groupTableBody);
          for (let groupKey in sectionData) {
            var groupData = sectionData[groupKey];
            if (groupData.length === 0) {
              continue;
            }
            var groupRow = document.createElement("tr");
            var groupHeader = document.createElement("th");
            groupHeader.classList.add("col-12", "text-center");
            groupHeader.colSpan = "2";
            groupHeader.textContent = groupKey;
            groupRow.appendChild(groupHeader);
            groupTableBody.appendChild(groupRow);
            var colCount = 0;
            var groupRow = document.createElement("tr");
            var textAlignClass = "text-center";
            for (let i = 0; i < groupData.length; i++) {
              var itemData = groupData[i];
              if (colCount == 2) {
                groupTable.appendChild(groupRow);
                groupRow = document.createElement("tr");
                textAlignClass = "text-center";
                colCount = 0;
              } else {
                textAlignClass = "text-center";
              }
              //if there is a : split it into 2 columns of data
              if (itemData.indexOf(":") > 2) {
                var itemValue = itemData.split(":");
                var itemValueRow = document.createElement("tr");
                var itemValueCol1 = document.createElement("td");
                itemValueCol1.classList.add("col-6", "text-end");
                itemValueCol1.textContent = itemValue[0];
                var itemValueCol2 = document.createElement("td");
                itemValueCol2.classList.add("col-6", "text-start");
                itemValueCol2.textContent = itemValue[1];
                itemValueRow.appendChild(itemValueCol1);
                itemValueRow.appendChild(itemValueCol2);
                groupTable.appendChild(itemValueRow);
              } else {
                var colspan = 1;
                if (i + 1 == groupData.length && colCount == 0) {
                  var colspan = 2;
                }
                var itemValueCol = document.createElement("td");
                itemValueCol.classList.add("col-6", textAlignClass);
                itemValueCol.colSpan = colspan;
                itemValueCol.textContent = itemData;
                groupRow.appendChild(itemValueCol);
                colCount++;
              }
            }
            groupTableBody.appendChild(groupRow);
          }
          this.currentCard.appendChild(groupTable);
        }
      }
    });
  }
  connect() {
    console.log("MemberMobileCardProfile connected");
    //this.loadCard();
  }
}
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["member-mobile-card-profile"] = MemberMobileCardProfile;

/***/ }),

/***/ "./assets/js/controllers/member-mobile-card-pwa-controller.js":
/*!********************************************************************!*\
  !*** ./assets/js/controllers/member-mobile-card-pwa-controller.js ***!
  \********************************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");

class MemberMobileCardPWA extends _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller {
  static targets = ["urlCache", "status", "refreshBtn"];
  static values = {
    swUrl: String
  };
  urlCacheTargetConnected() {
    this.urlCacheValue = JSON.parse(this.urlCacheTarget.textContent);
  }
  updateOnlineStatus() {
    const statusDiv = this.statusTarget;
    const refreshButton = this.refreshBtnTarget;
    if (navigator.onLine) {
      statusDiv.textContent = 'Online';
      statusDiv.classList.remove('bg-danger');
      statusDiv.classList.add('bg-success');
      refreshButton.hidden = false;
      refreshButton.click();
    } else {
      statusDiv.textContent = 'Offline';
      statusDiv.classList.remove('bg-success');
      statusDiv.classList.add('bg-danger');
      refreshButton.hidden = true;
    }
  }
  manageOnlineStatus() {
    this.updateOnlineStatus();
    window.addEventListener('online', this.updateOnlineStatus.bind(this));
    window.addEventListener('offline', this.updateOnlineStatus.bind(this));
    navigator.serviceWorker.register(this.swUrlValue).then(registration => {
      console.log('Service Worker registered with scope:', registration.scope);
      registration.active.postMessage({
        type: 'CACHE_URLS',
        payload: this.urlCacheValue
      });
    }, error => {
      console.log('Service Worker registration failed:', error);
    });
  }
  refreshPageIfOnline() {
    if (navigator.onLine) {
      window.location.reload();
    }
  }
  connect() {
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', this.manageOnlineStatus.bind(this));
    }
    setInterval(this.refreshPageIfOnline, 300000);
  }
  disconnect() {
    window.addEventListener('load', this.manageOnlineStatus.bind(this));
    window.removeEventListener('online', this.updateOnlineStatus.bind(this));
    window.removeEventListener('offline', this.updateOnlineStatus.bind(this));
  }
}
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["member-mobile-card-pwa"] = MemberMobileCardPWA;

/***/ }),

/***/ "./assets/js/controllers/member-unique-email-controller.js":
/*!*****************************************************************!*\
  !*** ./assets/js/controllers/member-unique-email-controller.js ***!
  \*****************************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");

class MemberUniqueEmail extends _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller {
  static values = {
    url: String
  };
  connect() {
    this.element.removeAttribute('oninput');
    this.element.removeAttribute('oninvalid');
    this.element.addEventListener('change', this.checkEmail.bind(this));
  }
  disconnect(event) {
    this.element.removeEventListener('change', this.checkEmail.bind(this));
  }
  checkEmail(event) {
    var email = this.element.value;
    if (email == '') {
      this.element.classList.remove('is-invalid');
      this.element.classList.remove('is-valid');
      this.element.setCustomValidity('');
      return;
    }
    var originalEmail = this.element.dataset.originalValue;
    if (email == originalEmail) {
      this.element.classList.add('is-valid');
      this.element.classList.remove('is-invalid');
      return;
    }
    var checkEmailUrl = this.urlValue + '?email=' + encodeURIComponent(email);
    fetch(checkEmailUrl).then(response => response.json()).then(data => {
      if (data) {
        this.element.classList.add('is-invalid');
        this.element.classList.remove('is-valid');
        this.element.setCustomValidity('This email address is already taken.');
      } else {
        this.element.classList.add('is-valid');
        this.element.classList.remove('is-invalid');
        this.element.setCustomValidity('');
      }
    });
  }
}
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["member-unique-email"] = MemberUniqueEmail;

/***/ }),

/***/ "./assets/js/controllers/member-verify-form-controller.js":
/*!****************************************************************!*\
  !*** ./assets/js/controllers/member-verify-form-controller.js ***!
  \****************************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");

class MemberVerifyForm extends _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller {
  static targets = ['scaMember', 'membershipNumber', 'membershipExpDate'];
  toggleParent(event) {
    var checked = event.target.checked;
    this.scaMemberTarget.disabled = !checked;
  }
  toggleMembership(event) {
    var checked = event.target.checked;
    this.membershipNumberTarget.disabled = !checked;
    this.membershipExpDateTarget.disabled = !checked;
  }
}
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["member-verify-form"] = MemberVerifyForm;

/***/ }),

/***/ "./assets/js/controllers/modal-opener-controller.js":
/*!**********************************************************!*\
  !*** ./assets/js/controllers/modal-opener-controller.js ***!
  \**********************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");

class ModalOpener extends _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller {
  static values = {
    modalBtn: String
  };
  modalBtnValueChanged() {
    let modal = document.getElementById(this.modalBtnValue);
    modal.click();
  }
}
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["modal-opener"] = ModalOpener;

/***/ }),

/***/ "./assets/js/controllers/nav-bar-controller.js":
/*!*****************************************************!*\
  !*** ./assets/js/controllers/nav-bar-controller.js ***!
  \*****************************************************/
/***/ (function(__unused_webpack_module, __unused_webpack_exports, __webpack_require__) {

const {
  Controller
} = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");
class NavBarController extends Controller {
  static targets = ["navHeader"];
  navHeaderClicked(event) {
    var state = event.target.getAttribute('aria-expanded');
    if (state === 'true') {
      var recordExpandUrl = event.target.getAttribute('data-expand-url');
      fetch(recordExpandUrl);
    } else {
      var recordCollapseUrl = event.target.getAttribute('data-collapse-url');
      fetch(recordCollapseUrl);
    }
  }
  navHeaderTargetConnected(event) {
    event.addEventListener('click', this.navHeaderClicked.bind(this));
  }
  navHeaderTargetDisconnected(event) {
    event.removeEventListener('click', this.navHeaderClicked.bind(this));
  }
}
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["nav-bar"] = NavBarController;

/***/ }),

/***/ "./assets/js/controllers/permission-add-role-controller.js":
/*!*****************************************************************!*\
  !*** ./assets/js/controllers/permission-add-role-controller.js ***!
  \*****************************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");

class PermissionAddRole extends _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller {
  static targets = ["role", "form", "submitBtn"];
  checkSubmitEnable() {
    let role = this.roleTarget.value;
    let roleId = Number(role.replace(/_/g, ""));
    if (roleId > 0) {
      this.submitBtnTarget.disabled = false;
      this.submitBtnTarget.focus();
    } else {
      this.submitBtnTarget.disabled = true;
    }
  }
}
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["permission-add-role"] = PermissionAddRole;

/***/ }),

/***/ "./assets/js/controllers/revoke-form-controller.js":
/*!*********************************************************!*\
  !*** ./assets/js/controllers/revoke-form-controller.js ***!
  \*********************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");

class RevokeForm extends _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller {
  static values = {
    url: String
  };
  static targets = ["submitBtn", "reason", "id"];
  static outlets = ["grid-btn"];
  setId(event) {
    this.idTarget.value = event.detail.id;
  }
  gridBtnOutletConnected(outlet, element) {
    outlet.addListener(this.setId.bind(this));
  }
  gridBtnOutletDisconnected(outlet) {
    outlet.removeListener(this.setId.bind(this));
  }
  checkReadyToSubmit() {
    let reasonValue = this.reasonTarget.value;
    if (reasonValue.length > 0) {
      this.submitBtnTarget.disabled = false;
    } else {
      this.submitBtnTarget.disabled = true;
    }
  }
  connect() {
    this.submitBtnTarget.disabled = true;
  }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["revoke-form"] = RevokeForm;

/***/ }),

/***/ "./assets/js/controllers/role-add-member-controller.js":
/*!*************************************************************!*\
  !*** ./assets/js/controllers/role-add-member-controller.js ***!
  \*************************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");

class RoleAddMember extends _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller {
  static targets = ["scaMember", "form", "submitBtn"];
  checkSubmitEnable() {
    let scaMember = this.scaMemberTarget.value;
    let memberId = Number(scaMember.replace(/_/g, ""));
    if (memberId > 0) {
      this.submitBtnTarget.disabled = false;
      this.submitBtnTarget.focus();
    } else {
      this.submitBtnTarget.disabled = true;
    }
  }
}
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["role-add-member"] = RoleAddMember;

/***/ }),

/***/ "./assets/js/controllers/role-add-permission-controller.js":
/*!*****************************************************************!*\
  !*** ./assets/js/controllers/role-add-permission-controller.js ***!
  \*****************************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");

class RoleAddPermission extends _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller {
  static targets = ["permission", "form", "submitBtn"];
  checkSubmitEnable() {
    let permission = this.permissionTarget.value;
    let permissionId = Number(permission.replace(/_/g, ""));
    if (permissionId > 0) {
      this.submitBtnTarget.disabled = false;
      this.submitBtnTarget.focus();
    } else {
      this.submitBtnTarget.disabled = true;
    }
  }
}
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["role-add-permission"] = RoleAddPermission;

/***/ }),

/***/ "./plugins/Activities/assets/js/controllers/approve-and-assign-auth-controller.js":
/*!****************************************************************************************!*\
  !*** ./plugins/Activities/assets/js/controllers/approve-and-assign-auth-controller.js ***!
  \****************************************************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");

class ActivitiesApproveAndAssignAuthorization extends _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller {
  static values = {
    url: String
  };
  static targets = ["approvers", "submitBtn", "id"];
  static outlets = ["grid-btn"];
  setId(event) {
    this.idTarget.value = event.detail.id;
    this.getApprovers();
  }
  gridBtnOutletConnected(outlet, element) {
    outlet.addListener(this.setId.bind(this));
  }
  gridBtnOutletDisconnected(outlet) {
    outlet.removeListener(this.setId.bind(this));
  }
  getApprovers() {
    if (this.hasApproversTarget) {
      this.approversTarget.value = "";
      let activityId = this.idTarget.value;
      let url = this.urlValue + "/" + activityId;
      fetch(url).then(response => response.json()).then(data => {
        let list = [];
        data.forEach(item => {
          list.push({
            value: item.id,
            text: item.sca_name
          });
        });
        this.approversTarget.options = list;
        this.submitBtnTarget.disabled = true;
        this.approversTarget.disabled = false;
      });
    }
  }
  checkReadyToSubmit() {
    let approverValue = this.approversTarget.value;
    let approverNum = parseInt(approverValue);
    if (approverNum > 0) {
      this.submitBtnTarget.disabled = false;
    } else {
      this.submitBtnTarget.disabled = true;
    }
  }
  submitBtnTargetConnected() {
    this.submitBtnTarget.disabled = true;
  }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["activities-approve-and-assign-auth"] = ActivitiesApproveAndAssignAuthorization;

/***/ }),

/***/ "./plugins/Activities/assets/js/controllers/renew-auth-controller.js":
/*!***************************************************************************!*\
  !*** ./plugins/Activities/assets/js/controllers/renew-auth-controller.js ***!
  \***************************************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");

class ActivitiesRenewAuthorization extends _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller {
  static values = {
    url: String
  };
  static targets = ["activity", "approvers", "submitBtn", "memberId", "id"];
  static outlets = ["grid-btn"];
  setId(event) {
    this.idTarget.value = event.detail.id;
    this.activityTarget.value = event.detail.activity;
    this.getApprovers();
  }
  gridBtnOutletConnected(outlet, element) {
    outlet.addListener(this.setId.bind(this));
  }
  gridBtnOutletDisconnected(outlet) {
    outlet.removeListener(this.setId.bind(this));
  }
  getApprovers() {
    if (this.hasApproversTarget) {
      this.approversTarget.value = "";
      let activityId = this.activityTarget.value;
      let url = this.urlValue + "/" + activityId + "/" + this.memberIdTarget.value;
      fetch(url).then(response => response.json()).then(data => {
        let list = [];
        data.forEach(item => {
          list.push({
            value: item.id,
            text: item.sca_name
          });
        });
        this.approversTarget.options = list;
        this.submitBtnTarget.disabled = true;
        this.approversTarget.disabled = false;
      });
    }
  }
  checkReadyToSubmit() {
    let approverValue = this.approversTarget.value;
    let approverNum = parseInt(approverValue);
    if (approverNum > 0) {
      this.submitBtnTarget.disabled = false;
    } else {
      this.submitBtnTarget.disabled = true;
    }
  }
  submitBtnTargetConnected() {
    this.submitBtnTarget.disabled = true;
  }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["activities-renew-auth"] = ActivitiesRenewAuthorization;

/***/ }),

/***/ "./plugins/Activities/assets/js/controllers/request-auth-controller.js":
/*!*****************************************************************************!*\
  !*** ./plugins/Activities/assets/js/controllers/request-auth-controller.js ***!
  \*****************************************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");

class ActivitiesRequestAuthorization extends _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller {
  static values = {
    url: String
  };
  static targets = ["activity", "approvers", "submitBtn", "memberId"];
  getApprovers(event) {
    this.approversTarget.value = "";
    let activityId = this.activityTarget.value;
    let url = this.urlValue + "/" + activityId + "/" + this.memberIdTarget.value;
    fetch(url).then(response => response.json()).then(data => {
      let list = [];
      data.forEach(item => {
        list.push({
          value: item.id,
          text: item.sca_name
        });
      });
      this.approversTarget.options = list;
      this.submitBtnTarget.disabled = true;
      this.approversTarget.disabled = false;
    });
  }
  acConnected() {
    if (this.hasApproversTarget) {
      this.approversTarget.disabled = true;
    }
  }
  checkReadyToSubmit() {
    let approverValue = this.approversTarget.value;
    let approverNum = parseInt(approverValue);
    if (approverNum > 0) {
      this.submitBtnTarget.disabled = false;
    } else {
      this.submitBtnTarget.disabled = true;
    }
  }
  submitBtnTargetConnected() {
    this.submitBtnTarget.disabled = true;
  }
  approversTargetConnected() {
    this.approversTarget.disabled = true;
  }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["activities-request-auth"] = ActivitiesRequestAuthorization;

/***/ }),

/***/ "./plugins/Awards/Assets/js/controllers/award-form-controller.js":
/*!***********************************************************************!*\
  !*** ./plugins/Awards/Assets/js/controllers/award-form-controller.js ***!
  \***********************************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");

class AwardsAwardForm extends _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller {
  static targets = ["new", "formValue", "displayList"];
  initialize() {
    this.items = [];
  }
  add(event) {
    event.preventDefault();
    if (!this.newTarget.value) {
      return;
    }
    if (this.items.includes(this.newTarget.value)) {
      return;
    }
    let item = this.newTarget.value;
    this.items.push(item);
    this.createListItem(KMP_utils.sanitizeString(item));
    this.formValueTarget.value = JSON.stringify(this.items);
    this.newTarget.value = '';
  }
  remove(event) {
    event.preventDefault();
    let id = event.target.getAttribute('data-id');
    this.items = this.items.filter(item => {
      return item !== id;
    });
    this.formValueTarget.value = JSON.stringify(this.items);
    event.target.parentElement.remove();
  }
  connect() {
    if (this.formValueTarget.value && this.formValueTarget.value.length > 0) {
      this.items = JSON.parse(this.formValueTarget.value);
      this.items.forEach(item => {
        //create a remove button
        this.createListItem(item);
      });
    }
  }
  createListItem(item) {
    let removeButton = document.createElement('button');
    removeButton.innerHTML = 'Remove';
    removeButton.setAttribute('data-action', 'click->awards-award-form#remove');
    removeButton.setAttribute('data-id', item);
    removeButton.setAttribute('class', 'btn btn-danger btn-sm');
    removeButton.setAttribute('type', 'button');
    //create a list item
    let listItem = document.createElement('li');
    let span = document.createElement('span');
    span.innerHTML = item;
    span.setAttribute('class', 'ms-2 me-auto');
    listItem.innerHTML = item;
    listItem.setAttribute('class', 'list-group-item d-flex justify-content-between align-items-start');
    listItem.appendChild(removeButton);
    this.displayListTarget.appendChild(listItem);
  }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["awards-award-form"] = AwardsAwardForm;

/***/ }),

/***/ "./plugins/Awards/Assets/js/controllers/recommendation-form-controller.js":
/*!********************************************************************************!*\
  !*** ./plugins/Awards/Assets/js/controllers/recommendation-form-controller.js ***!
  \********************************************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");

class AwardsRecommendationForm extends _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller {
  static targets = ["scaMember", "notFound", "branch", "callIntoCourt", "courtAvailability", "externalLinks", "awardDescriptions", "award", "reason", "events", "specialty"];
  static values = {
    publicProfileUrl: String,
    awardListUrl: String
  };
  submit(event) {
    this.callIntoCourtTarget.disabled = false;
    this.courtAvailabilityTarget.disabled = false;
    this.notFoundTarget.disabled = false;
  }
  setAward(event) {
    let awardId = event.target.dataset.awardId;
    this.awardTarget.value = awardId;
    this.populateSpecialties(event);
  }
  populateAwardDescriptions(event) {
    let url = this.awardListUrlValue + "/" + event.target.value;
    fetch(url).then(response => response.json()).then(data => {
      this.awardDescriptionsTarget.innerHTML = "";
      let tabButtons = document.createElement("ul");
      tabButtons.classList.add("nav", "nav-pills");
      tabButtons.setAttribute("role", "tablist");
      let tabContentArea = document.createElement("div");
      tabContentArea.classList.add("tab-content");
      tabContentArea.classList.add("border");
      tabContentArea.classList.add("border-light-subtle");
      tabContentArea.classList.add("p-2");
      tabContentArea.innerHTML = "";
      this.awardTarget.value = "";
      let active = "active";
      let show = "show";
      let selected = "true";
      let awardList = [];
      if (data.length > 0) {
        data.forEach(function (award) {
          //create list item
          awardList.push({
            value: award.id,
            text: award.name,
            specialties: award.specialties
          });
          //create tab info
          var tabButton = document.createElement("li");
          tabButton.classList.add("nav-item");
          tabButton.setAttribute("role", "presentation");
          var button = document.createElement("button");
          button.classList.add("nav-link");
          if (active == "active") {
            button.classList.add("active");
          }
          button.setAttribute("data-action", "click->awards-rec-form#setAward");
          button.setAttribute("id", "award_" + award.id + "_btn");
          button.setAttribute("data-bs-toggle", "tab");
          button.setAttribute("data-bs-target", "#award_" + award.id);
          button.setAttribute('data-award-id', award.id);
          button.setAttribute("type", "button");
          button.setAttribute("role", "tab");
          button.setAttribute("aria-controls", "award_" + award.id);
          button.setAttribute("aria-selected", selected);
          button.innerHTML = award.name;
          tabButton.appendChild(button);
          var tabContent = document.createElement("div");
          tabContent.classList.add("tab-pane");
          tabContent.classList.add("fade");
          if (show == "show") {
            tabContent.classList.add("show");
          }
          if (active == "active") {
            tabContent.classList.add("active");
          }
          tabContent.setAttribute("id", "award_" + award.id);
          tabContent.setAttribute("role", "tabpanel");
          tabContent.setAttribute("aria-labelledby", "award_" + award.id + "_btn");
          tabContent.innerHTML = award.name + ": " + award.description;
          active = "";
          show = "";
          selected = "false";
          tabButtons.append(tabButton);
          tabContentArea.append(tabContent);
        });
        this.awardDescriptionsTarget.appendChild(tabButtons);
        this.awardDescriptionsTarget.appendChild(tabContentArea);
        this.awardTarget.options = awardList;
        this.awardTarget.disabled = false;
      } else {
        awardComboData.appendChild(li);
        this.awardTarget.options = [{
          id: "No awards available",
          text: "No awards available"
        }];
        this.awardTarget.value = "No awards available";
        this.awardTarget.disabled = true;
      }
    });
  }
  populateSpecialties(event) {
    let awardId = this.awardTarget.value;
    let options = this.awardTarget.options;
    let award = this.awardTarget.options.find(award => award.value == awardId);
    let specialtyArray = [];
    if (award.specialties != null && award.specialties.length > 0) {
      award.specialties.forEach(function (specialty) {
        specialtyArray.push({
          value: specialty,
          text: specialty
        });
      });
      this.specialtyTarget.options = specialtyArray;
      this.specialtyTarget.value = "";
      this.specialtyTarget.disabled = false;
      this.specialtyTarget.hidden = false;
    } else {
      this.specialtyTarget.options = [{
        value: "No specialties available",
        text: "No specialties available"
      }];
      this.specialtyTarget.value = "No specialties available";
      this.specialtyTarget.disabled = true;
      this.specialtyTarget.hidden = true;
    }
  }
  loadScaMemberInfo(event) {
    //reset member metadata area
    this.externalLinksTarget.innerHTML = "";
    this.courtAvailabilityTarget.value = "";
    this.callIntoCourtTarget.value = "";
    this.callIntoCourtTarget.disabled = false;
    this.courtAvailabilityTarget.disabled = false;
    let memberId = Number(event.target.value.replace(/_/g, ""));
    if (memberId > 0) {
      this.notFoundTarget.checked = false;
      this.branchTarget.hidden = true;
      this.branchTarget.disabled = true;
      this.loadMember(memberId);
    } else {
      this.notFoundTarget.checked = true;
      this.branchTarget.hidden = false;
      this.branchTarget.disabled = false;
      this.branchTarget.focus();
    }
  }
  loadMember(memberId) {
    let url = this.publicProfileUrlValue + "/" + memberId;
    fetch(url).then(response => response.json()).then(data => {
      this.callIntoCourtTarget.value = data.additional_info.CallIntoCourt;
      this.courtAvailabilityTarget.value = data.additional_info.CourtAvailability;
      if (this.callIntoCourtTarget.value != "") {
        this.callIntoCourtTarget.disabled = true;
      } else {
        this.callIntoCourtTarget.disabled = false;
      }
      if (this.courtAvailabilityTarget.value != "") {
        this.courtAvailabilityTarget.disabled = true;
      } else {
        this.courtAvailabilityTarget.disabled = false;
      }
      this.externalLinksTarget.innerHTML = "";
      let keys = Object.keys(data.external_links);
      if (keys.length > 0) {
        var LinksTitle = document.createElement("div");
        LinksTitle.innerHTML = "<h5>Public Links</h5>";
        LinksTitle.classList.add("col-12");
        this.externalLinksTarget.appendChild(LinksTitle);
        for (let key in data.external_links) {
          let div = document.createElement("div");
          div.classList.add("col-12");
          let a = document.createElement("a");
          a.href = data.external_links[key];
          a.text = key;
          a.target = "_blank";
          div.appendChild(a);
          this.externalLinksTarget.appendChild(div);
        }
      } else {
        var noLink = document.createElement("div");
        noLink.innerHTML = "<h5>No links available</h5>";
        noLink.classList.add("col-12");
        this.externalLinksTarget.appendChild(noLink);
      }
    });
  }
  acConnected(event) {
    var target = event.detail["awardsRecFormTarget"];
    switch (target) {
      case "branch":
        this.branchTarget.disabled = true;
        this.branchTarget.hidden = true;
        this.branchTarget.value = "";
        break;
      case "award":
        this.awardTarget.disabled = true;
        this.awardTarget.value = "Select Award Type First";
        break;
      case "scaMember":
        this.scaMemberTarget.value = "";
        break;
      case "specialty":
        this.specialtyTarget.value = "Select Award First";
        this.specialtyTarget.disabled = true;
        this.specialtyTarget.hidden = true;
        break;
      default:
        event.target.value = "";
        break;
    }
  }
  connect() {
    this.notFoundTarget.checked = false;
    this.notFoundTarget.disabled = true;
    this.reasonTarget.value = "";
    this.eventsTargets.forEach(element => {
      element.checked = false;
    });
  }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["awards-rec-form"] = AwardsRecommendationForm;

/***/ }),

/***/ "./plugins/GitHubIssueSubmitter/assets/js/controllers/github-submitter-controller.js":
/*!*******************************************************************************************!*\
  !*** ./plugins/GitHubIssueSubmitter/assets/js/controllers/github-submitter-controller.js ***!
  \*******************************************************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");

class GitHubSubmitter extends _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller {
  static targets = ["success", "formBlock", "submitBtn", "issueLink", "form", "modal"];
  static values = {
    url: String
  };
  submit(event) {
    event.preventDefault();
    let url = this.urlValue;
    let form = this.formTarget;
    let formData = new FormData(form);
    fetch(url, {
      method: 'POST',
      body: formData
    }).then(response => {
      if (response.ok) {
        return response.json();
      } else {
        throw new Error('An error occurred while creating the issue.');
      }
    }).then(data => {
      if (data.message) {
        alert("Error: " + data.message);
        return;
      }
      form.reset();
      this.formBlockTarget.style.display = 'none';
      this.submitBtnTarget.style.display = 'none';
      this.issueLinkTarget.href = data.url;
      this.successTarget.style.display = 'block';
    }).catch(error => {
      console.error(error);
      alert('An error occurred while creating the issue.');
    });
  }
  modalTargetConnected() {
    this.modalTarget.addEventListener('hidden.bs.modal', () => {
      this.formBlockTarget.style.display = 'block';
      this.successTarget.style.display = 'none';
      this.submitBtnTarget.style.display = 'block';
    });
  }
  modalTargetDisconnected() {
    this.modalTarget.removeEventListener('hidden.bs.modal', () => {
      this.formBlockTarget.style.display = 'block';
      this.successTarget.style.display = 'none';
      this.submitBtnTarget.style.display = 'block';
    });
  }
  connect() {
    this.formBlockTarget.style.display = 'block';
    this.successTarget.style.display = 'none';
    this.submitBtnTarget.style.display = 'block';
  }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["github-submitter"] = GitHubSubmitter;

/***/ }),

/***/ "./plugins/Officers/assets/js/controllers/assign-officer-controller.js":
/*!*****************************************************************************!*\
  !*** ./plugins/Officers/assets/js/controllers/assign-officer-controller.js ***!
  \*****************************************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");

class OfficersAssignOfficer extends _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller {
  static values = {
    url: String
  };
  static targets = ["assignee", "submitBtn", "deputyDescBlock", "deputyDesc", "office", "endDateBlock", "endDate"];
  static outlets = ["grid-btn"];
  setOfficeQuestions() {
    this.deputyDescBlockTarget.classList.add('d-none');
    this.endDateBlockTarget.classList.add('d-none');
    this.endDateTarget.disabled = true;
    this.deputyDescTarget.disabled = true;
    var officeVal = this.officeTarget.value;
    var office = this.officeTarget.options.find(option => option.value == officeVal);
    if (office) {
      if (office.data.is_deputy) {
        this.deputyDescBlockTarget.classList.remove('d-none');
        this.endDateBlockTarget.classList.remove('d-none');
        this.endDateTarget.disabled = false;
        this.deputyDescTarget.disabled = false;
      }
      this.checkReadyToSubmit();
      return;
    }
  }
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
  submitBtnTargetConnected() {
    this.submitBtnTarget.disabled = true;
  }
  endDateTargetConnected() {
    this.endDateTarget.disabled = true;
  }
  deputyDescTargetConnected() {
    this.deputyDescTarget.disabled = true;
  }
  connect() {
    this.deputyDescBlockTarget.classList.add('d-none');
    this.endDateBlockTarget.classList.add('d-none');
  }
}
// add to window.Controllers with a name of the controller
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["officers-assign-officer"] = OfficersAssignOfficer;

/***/ }),

/***/ "./assets/css/app.css":
/*!****************************!*\
  !*** ./assets/css/app.css ***!
  \****************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./assets/css/signin.css":
/*!*******************************!*\
  !*** ./assets/css/signin.css ***!
  \*******************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./assets/css/cover.css":
/*!******************************!*\
  !*** ./assets/css/cover.css ***!
  \******************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./assets/css/dashboard.css":
/*!**********************************!*\
  !*** ./assets/css/dashboard.css ***!
  \**********************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ })

},
/******/ function(__webpack_require__) { // webpackRuntimeModules
/******/ var __webpack_exec__ = function(moduleId) { return __webpack_require__(__webpack_require__.s = moduleId); }
/******/ __webpack_require__.O(0, ["js/core","css/app","css/cover","css/signin","css/dashboard"], function() { return __webpack_exec__("./assets/js/controllers/app-setting-form-controller.js"), __webpack_exec__("./assets/js/controllers/auto-complete-controller.js"), __webpack_exec__("./assets/js/controllers/detail-tabs-controller.js"), __webpack_exec__("./assets/js/controllers/grid-button-controller.js"), __webpack_exec__("./assets/js/controllers/image-preview-controller.js"), __webpack_exec__("./assets/js/controllers/member-card-profile-controller.js"), __webpack_exec__("./assets/js/controllers/member-mobile-card-profile-controller.js"), __webpack_exec__("./assets/js/controllers/member-mobile-card-pwa-controller.js"), __webpack_exec__("./assets/js/controllers/member-unique-email-controller.js"), __webpack_exec__("./assets/js/controllers/member-verify-form-controller.js"), __webpack_exec__("./assets/js/controllers/modal-opener-controller.js"), __webpack_exec__("./assets/js/controllers/nav-bar-controller.js"), __webpack_exec__("./assets/js/controllers/permission-add-role-controller.js"), __webpack_exec__("./assets/js/controllers/revoke-form-controller.js"), __webpack_exec__("./assets/js/controllers/role-add-member-controller.js"), __webpack_exec__("./assets/js/controllers/role-add-permission-controller.js"), __webpack_exec__("./plugins/Activities/assets/js/controllers/approve-and-assign-auth-controller.js"), __webpack_exec__("./plugins/Activities/assets/js/controllers/renew-auth-controller.js"), __webpack_exec__("./plugins/Activities/assets/js/controllers/request-auth-controller.js"), __webpack_exec__("./plugins/Awards/Assets/js/controllers/award-form-controller.js"), __webpack_exec__("./plugins/Awards/Assets/js/controllers/recommendation-form-controller.js"), __webpack_exec__("./plugins/GitHubIssueSubmitter/assets/js/controllers/github-submitter-controller.js"), __webpack_exec__("./plugins/Officers/assets/js/controllers/assign-officer-controller.js"), __webpack_exec__("./assets/css/app.css"), __webpack_exec__("./assets/css/signin.css"), __webpack_exec__("./assets/css/cover.css"), __webpack_exec__("./assets/css/dashboard.css"); });
/******/ var __webpack_exports__ = __webpack_require__.O();
/******/ }
]);
//# sourceMappingURL=controllers.js.map