"use strict";
(self["webpackChunk"] = self["webpackChunk"] || []).push([["/js/controllers"],{

/***/ "./assets/js/controllers/app-setting-form-controller.js":
/*!**************************************************************!*\
  !*** ./assets/js/controllers/app-setting-form-controller.js ***!
  \**************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _classCallCheck(a, n) { if (!(a instanceof n)) throw new TypeError("Cannot call a class as a function"); }
function _defineProperties(e, r) { for (var t = 0; t < r.length; t++) { var o = r[t]; o.enumerable = o.enumerable || !1, o.configurable = !0, "value" in o && (o.writable = !0), Object.defineProperty(e, _toPropertyKey(o.key), o); } }
function _createClass(e, r, t) { return r && _defineProperties(e.prototype, r), t && _defineProperties(e, t), Object.defineProperty(e, "prototype", { writable: !1 }), e; }
function _callSuper(t, o, e) { return o = _getPrototypeOf(o), _possibleConstructorReturn(t, _isNativeReflectConstruct() ? Reflect.construct(o, e || [], _getPrototypeOf(t).constructor) : o.apply(t, e)); }
function _possibleConstructorReturn(t, e) { if (e && ("object" == _typeof(e) || "function" == typeof e)) return e; if (void 0 !== e) throw new TypeError("Derived constructors may only return object or undefined"); return _assertThisInitialized(t); }
function _assertThisInitialized(e) { if (void 0 === e) throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); return e; }
function _isNativeReflectConstruct() { try { var t = !Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], function () {})); } catch (t) {} return (_isNativeReflectConstruct = function _isNativeReflectConstruct() { return !!t; })(); }
function _getPrototypeOf(t) { return _getPrototypeOf = Object.setPrototypeOf ? Object.getPrototypeOf.bind() : function (t) { return t.__proto__ || Object.getPrototypeOf(t); }, _getPrototypeOf(t); }
function _inherits(t, e) { if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function"); t.prototype = Object.create(e && e.prototype, { constructor: { value: t, writable: !0, configurable: !0 } }), Object.defineProperty(t, "prototype", { writable: !1 }), e && _setPrototypeOf(t, e); }
function _setPrototypeOf(t, e) { return _setPrototypeOf = Object.setPrototypeOf ? Object.setPrototypeOf.bind() : function (t, e) { return t.__proto__ = e, t; }, _setPrototypeOf(t, e); }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }

var AppSettingForm = /*#__PURE__*/function (_Controller) {
  function AppSettingForm() {
    _classCallCheck(this, AppSettingForm);
    return _callSuper(this, AppSettingForm, arguments);
  }
  _inherits(AppSettingForm, _Controller);
  return _createClass(AppSettingForm, [{
    key: "submit",
    value: function submit(event) {
      event.preventDefault();
      this.formTarget.submit();
    }
  }, {
    key: "enableSubmit",
    value: function enableSubmit() {
      this.submitBtnTarget.disabled = false;
      this.submitBtnTarget.focus();
    }
  }]);
}(_hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller);
_defineProperty(AppSettingForm, "targets", ["submitBtn", "form"]);
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["app-setting-form"] = AppSettingForm;

/***/ }),

/***/ "./assets/js/controllers/auto-complete-controller.js":
/*!***********************************************************!*\
  !*** ./assets/js/controllers/auto-complete-controller.js ***!
  \***********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");
function _toConsumableArray(r) { return _arrayWithoutHoles(r) || _iterableToArray(r) || _unsupportedIterableToArray(r) || _nonIterableSpread(); }
function _nonIterableSpread() { throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }
function _iterableToArray(r) { if ("undefined" != typeof Symbol && null != r[Symbol.iterator] || null != r["@@iterator"]) return Array.from(r); }
function _arrayWithoutHoles(r) { if (Array.isArray(r)) return _arrayLikeToArray(r); }
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _regeneratorRuntime() { "use strict"; /*! regenerator-runtime -- Copyright (c) 2014-present, Facebook, Inc. -- license (MIT): https://github.com/facebook/regenerator/blob/main/LICENSE */ _regeneratorRuntime = function _regeneratorRuntime() { return e; }; var t, e = {}, r = Object.prototype, n = r.hasOwnProperty, o = Object.defineProperty || function (t, e, r) { t[e] = r.value; }, i = "function" == typeof Symbol ? Symbol : {}, a = i.iterator || "@@iterator", c = i.asyncIterator || "@@asyncIterator", u = i.toStringTag || "@@toStringTag"; function define(t, e, r) { return Object.defineProperty(t, e, { value: r, enumerable: !0, configurable: !0, writable: !0 }), t[e]; } try { define({}, ""); } catch (t) { define = function define(t, e, r) { return t[e] = r; }; } function wrap(t, e, r, n) { var i = e && e.prototype instanceof Generator ? e : Generator, a = Object.create(i.prototype), c = new Context(n || []); return o(a, "_invoke", { value: makeInvokeMethod(t, r, c) }), a; } function tryCatch(t, e, r) { try { return { type: "normal", arg: t.call(e, r) }; } catch (t) { return { type: "throw", arg: t }; } } e.wrap = wrap; var h = "suspendedStart", l = "suspendedYield", f = "executing", s = "completed", y = {}; function Generator() {} function GeneratorFunction() {} function GeneratorFunctionPrototype() {} var p = {}; define(p, a, function () { return this; }); var d = Object.getPrototypeOf, v = d && d(d(values([]))); v && v !== r && n.call(v, a) && (p = v); var g = GeneratorFunctionPrototype.prototype = Generator.prototype = Object.create(p); function defineIteratorMethods(t) { ["next", "throw", "return"].forEach(function (e) { define(t, e, function (t) { return this._invoke(e, t); }); }); } function AsyncIterator(t, e) { function invoke(r, o, i, a) { var c = tryCatch(t[r], t, o); if ("throw" !== c.type) { var u = c.arg, h = u.value; return h && "object" == _typeof(h) && n.call(h, "__await") ? e.resolve(h.__await).then(function (t) { invoke("next", t, i, a); }, function (t) { invoke("throw", t, i, a); }) : e.resolve(h).then(function (t) { u.value = t, i(u); }, function (t) { return invoke("throw", t, i, a); }); } a(c.arg); } var r; o(this, "_invoke", { value: function value(t, n) { function callInvokeWithMethodAndArg() { return new e(function (e, r) { invoke(t, n, e, r); }); } return r = r ? r.then(callInvokeWithMethodAndArg, callInvokeWithMethodAndArg) : callInvokeWithMethodAndArg(); } }); } function makeInvokeMethod(e, r, n) { var o = h; return function (i, a) { if (o === f) throw Error("Generator is already running"); if (o === s) { if ("throw" === i) throw a; return { value: t, done: !0 }; } for (n.method = i, n.arg = a;;) { var c = n.delegate; if (c) { var u = maybeInvokeDelegate(c, n); if (u) { if (u === y) continue; return u; } } if ("next" === n.method) n.sent = n._sent = n.arg;else if ("throw" === n.method) { if (o === h) throw o = s, n.arg; n.dispatchException(n.arg); } else "return" === n.method && n.abrupt("return", n.arg); o = f; var p = tryCatch(e, r, n); if ("normal" === p.type) { if (o = n.done ? s : l, p.arg === y) continue; return { value: p.arg, done: n.done }; } "throw" === p.type && (o = s, n.method = "throw", n.arg = p.arg); } }; } function maybeInvokeDelegate(e, r) { var n = r.method, o = e.iterator[n]; if (o === t) return r.delegate = null, "throw" === n && e.iterator["return"] && (r.method = "return", r.arg = t, maybeInvokeDelegate(e, r), "throw" === r.method) || "return" !== n && (r.method = "throw", r.arg = new TypeError("The iterator does not provide a '" + n + "' method")), y; var i = tryCatch(o, e.iterator, r.arg); if ("throw" === i.type) return r.method = "throw", r.arg = i.arg, r.delegate = null, y; var a = i.arg; return a ? a.done ? (r[e.resultName] = a.value, r.next = e.nextLoc, "return" !== r.method && (r.method = "next", r.arg = t), r.delegate = null, y) : a : (r.method = "throw", r.arg = new TypeError("iterator result is not an object"), r.delegate = null, y); } function pushTryEntry(t) { var e = { tryLoc: t[0] }; 1 in t && (e.catchLoc = t[1]), 2 in t && (e.finallyLoc = t[2], e.afterLoc = t[3]), this.tryEntries.push(e); } function resetTryEntry(t) { var e = t.completion || {}; e.type = "normal", delete e.arg, t.completion = e; } function Context(t) { this.tryEntries = [{ tryLoc: "root" }], t.forEach(pushTryEntry, this), this.reset(!0); } function values(e) { if (e || "" === e) { var r = e[a]; if (r) return r.call(e); if ("function" == typeof e.next) return e; if (!isNaN(e.length)) { var o = -1, i = function next() { for (; ++o < e.length;) if (n.call(e, o)) return next.value = e[o], next.done = !1, next; return next.value = t, next.done = !0, next; }; return i.next = i; } } throw new TypeError(_typeof(e) + " is not iterable"); } return GeneratorFunction.prototype = GeneratorFunctionPrototype, o(g, "constructor", { value: GeneratorFunctionPrototype, configurable: !0 }), o(GeneratorFunctionPrototype, "constructor", { value: GeneratorFunction, configurable: !0 }), GeneratorFunction.displayName = define(GeneratorFunctionPrototype, u, "GeneratorFunction"), e.isGeneratorFunction = function (t) { var e = "function" == typeof t && t.constructor; return !!e && (e === GeneratorFunction || "GeneratorFunction" === (e.displayName || e.name)); }, e.mark = function (t) { return Object.setPrototypeOf ? Object.setPrototypeOf(t, GeneratorFunctionPrototype) : (t.__proto__ = GeneratorFunctionPrototype, define(t, u, "GeneratorFunction")), t.prototype = Object.create(g), t; }, e.awrap = function (t) { return { __await: t }; }, defineIteratorMethods(AsyncIterator.prototype), define(AsyncIterator.prototype, c, function () { return this; }), e.AsyncIterator = AsyncIterator, e.async = function (t, r, n, o, i) { void 0 === i && (i = Promise); var a = new AsyncIterator(wrap(t, r, n, o), i); return e.isGeneratorFunction(r) ? a : a.next().then(function (t) { return t.done ? t.value : a.next(); }); }, defineIteratorMethods(g), define(g, u, "Generator"), define(g, a, function () { return this; }), define(g, "toString", function () { return "[object Generator]"; }), e.keys = function (t) { var e = Object(t), r = []; for (var n in e) r.push(n); return r.reverse(), function next() { for (; r.length;) { var t = r.pop(); if (t in e) return next.value = t, next.done = !1, next; } return next.done = !0, next; }; }, e.values = values, Context.prototype = { constructor: Context, reset: function reset(e) { if (this.prev = 0, this.next = 0, this.sent = this._sent = t, this.done = !1, this.delegate = null, this.method = "next", this.arg = t, this.tryEntries.forEach(resetTryEntry), !e) for (var r in this) "t" === r.charAt(0) && n.call(this, r) && !isNaN(+r.slice(1)) && (this[r] = t); }, stop: function stop() { this.done = !0; var t = this.tryEntries[0].completion; if ("throw" === t.type) throw t.arg; return this.rval; }, dispatchException: function dispatchException(e) { if (this.done) throw e; var r = this; function handle(n, o) { return a.type = "throw", a.arg = e, r.next = n, o && (r.method = "next", r.arg = t), !!o; } for (var o = this.tryEntries.length - 1; o >= 0; --o) { var i = this.tryEntries[o], a = i.completion; if ("root" === i.tryLoc) return handle("end"); if (i.tryLoc <= this.prev) { var c = n.call(i, "catchLoc"), u = n.call(i, "finallyLoc"); if (c && u) { if (this.prev < i.catchLoc) return handle(i.catchLoc, !0); if (this.prev < i.finallyLoc) return handle(i.finallyLoc); } else if (c) { if (this.prev < i.catchLoc) return handle(i.catchLoc, !0); } else { if (!u) throw Error("try statement without catch or finally"); if (this.prev < i.finallyLoc) return handle(i.finallyLoc); } } } }, abrupt: function abrupt(t, e) { for (var r = this.tryEntries.length - 1; r >= 0; --r) { var o = this.tryEntries[r]; if (o.tryLoc <= this.prev && n.call(o, "finallyLoc") && this.prev < o.finallyLoc) { var i = o; break; } } i && ("break" === t || "continue" === t) && i.tryLoc <= e && e <= i.finallyLoc && (i = null); var a = i ? i.completion : {}; return a.type = t, a.arg = e, i ? (this.method = "next", this.next = i.finallyLoc, y) : this.complete(a); }, complete: function complete(t, e) { if ("throw" === t.type) throw t.arg; return "break" === t.type || "continue" === t.type ? this.next = t.arg : "return" === t.type ? (this.rval = this.arg = t.arg, this.method = "return", this.next = "end") : "normal" === t.type && e && (this.next = e), y; }, finish: function finish(t) { for (var e = this.tryEntries.length - 1; e >= 0; --e) { var r = this.tryEntries[e]; if (r.finallyLoc === t) return this.complete(r.completion, r.afterLoc), resetTryEntry(r), y; } }, "catch": function _catch(t) { for (var e = this.tryEntries.length - 1; e >= 0; --e) { var r = this.tryEntries[e]; if (r.tryLoc === t) { var n = r.completion; if ("throw" === n.type) { var o = n.arg; resetTryEntry(r); } return o; } } throw Error("illegal catch attempt"); }, delegateYield: function delegateYield(e, r, n) { return this.delegate = { iterator: values(e), resultName: r, nextLoc: n }, "next" === this.method && (this.arg = t), y; } }, e; }
function _createForOfIteratorHelper(r, e) { var t = "undefined" != typeof Symbol && r[Symbol.iterator] || r["@@iterator"]; if (!t) { if (Array.isArray(r) || (t = _unsupportedIterableToArray(r)) || e && r && "number" == typeof r.length) { t && (r = t); var _n = 0, F = function F() {}; return { s: F, n: function n() { return _n >= r.length ? { done: !0 } : { done: !1, value: r[_n++] }; }, e: function e(r) { throw r; }, f: F }; } throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); } var o, a = !0, u = !1; return { s: function s() { t = t.call(r); }, n: function n() { var r = t.next(); return a = r.done, r; }, e: function e(r) { u = !0, o = r; }, f: function f() { try { a || null == t["return"] || t["return"](); } finally { if (u) throw o; } } }; }
function _unsupportedIterableToArray(r, a) { if (r) { if ("string" == typeof r) return _arrayLikeToArray(r, a); var t = {}.toString.call(r).slice(8, -1); return "Object" === t && r.constructor && (t = r.constructor.name), "Map" === t || "Set" === t ? Array.from(r) : "Arguments" === t || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(t) ? _arrayLikeToArray(r, a) : void 0; } }
function _arrayLikeToArray(r, a) { (null == a || a > r.length) && (a = r.length); for (var e = 0, n = Array(a); e < a; e++) n[e] = r[e]; return n; }
function asyncGeneratorStep(n, t, e, r, o, a, c) { try { var i = n[a](c), u = i.value; } catch (n) { return void e(n); } i.done ? t(u) : Promise.resolve(u).then(r, o); }
function _asyncToGenerator(n) { return function () { var t = this, e = arguments; return new Promise(function (r, o) { var a = n.apply(t, e); function _next(n) { asyncGeneratorStep(a, r, o, _next, _throw, "next", n); } function _throw(n) { asyncGeneratorStep(a, r, o, _next, _throw, "throw", n); } _next(void 0); }); }; }
function _classCallCheck(a, n) { if (!(a instanceof n)) throw new TypeError("Cannot call a class as a function"); }
function _defineProperties(e, r) { for (var t = 0; t < r.length; t++) { var o = r[t]; o.enumerable = o.enumerable || !1, o.configurable = !0, "value" in o && (o.writable = !0), Object.defineProperty(e, _toPropertyKey(o.key), o); } }
function _createClass(e, r, t) { return r && _defineProperties(e.prototype, r), t && _defineProperties(e, t), Object.defineProperty(e, "prototype", { writable: !1 }), e; }
function _callSuper(t, o, e) { return o = _getPrototypeOf(o), _possibleConstructorReturn(t, _isNativeReflectConstruct() ? Reflect.construct(o, e || [], _getPrototypeOf(t).constructor) : o.apply(t, e)); }
function _possibleConstructorReturn(t, e) { if (e && ("object" == _typeof(e) || "function" == typeof e)) return e; if (void 0 !== e) throw new TypeError("Derived constructors may only return object or undefined"); return _assertThisInitialized(t); }
function _assertThisInitialized(e) { if (void 0 === e) throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); return e; }
function _isNativeReflectConstruct() { try { var t = !Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], function () {})); } catch (t) {} return (_isNativeReflectConstruct = function _isNativeReflectConstruct() { return !!t; })(); }
function _getPrototypeOf(t) { return _getPrototypeOf = Object.setPrototypeOf ? Object.getPrototypeOf.bind() : function (t) { return t.__proto__ || Object.getPrototypeOf(t); }, _getPrototypeOf(t); }
function _inherits(t, e) { if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function"); t.prototype = Object.create(e && e.prototype, { constructor: { value: t, writable: !0, configurable: !0 } }), Object.defineProperty(t, "prototype", { writable: !1 }), e && _setPrototypeOf(t, e); }
function _setPrototypeOf(t, e) { return _setPrototypeOf = Object.setPrototypeOf ? Object.setPrototypeOf.bind() : function (t, e) { return t.__proto__ = e, t; }, _setPrototypeOf(t, e); }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }

var optionSelector = "[role='option']:not([aria-disabled])";
var activeSelector = "[aria-selected='true']";
var AutoComplete = /*#__PURE__*/function (_Controller) {
  function AutoComplete() {
    var _this;
    _classCallCheck(this, AutoComplete);
    for (var _len = arguments.length, args = new Array(_len), _key = 0; _key < _len; _key++) {
      args[_key] = arguments[_key];
    }
    _this = _callSuper(this, AutoComplete, [].concat(args));
    _defineProperty(_this, "onInputChangeTriggered", function (event) {
      event.stopPropagation();
    });
    _defineProperty(_this, "onInputClick", function (event) {
      if (_this.hasDataListTarget) {
        var query = _this.inputTarget.value.trim();
        _this.fetchResults(query);
      }
    });
    _defineProperty(_this, "onKeydown", function (event) {
      var handler = _this["on".concat(event.key, "Keydown")];
      if (handler) handler(event);
    });
    _defineProperty(_this, "onEscapeKeydown", function (event) {
      if (!_this.resultsShown) return;
      _this.hideAndRemoveOptions();
      event.stopPropagation();
      event.preventDefault();
    });
    _defineProperty(_this, "onArrowDownKeydown", function (event) {
      var item = _this.sibling(true);
      if (item) _this.select(item);
      event.preventDefault();
    });
    _defineProperty(_this, "onArrowUpKeydown", function (event) {
      var item = _this.sibling(false);
      if (item) _this.select(item);
      event.preventDefault();
    });
    _defineProperty(_this, "onTabKeydown", function (event) {
      var selected = _this.selectedOption;
      if (selected) _this.commit(selected);
    });
    _defineProperty(_this, "onEnterKeydown", function (event) {
      var selected = _this.selectedOption;
      if (selected && _this.resultsShown) {
        _this.commit(selected);
        if (!_this.hasSubmitOnEnterValue) {
          event.preventDefault();
        }
      }
    });
    _defineProperty(_this, "onInputBlur", function () {
      if (_this.mouseDown) return;
      if (_this.state !== "finished" && _this.state !== "start") {
        if (_this.allowOtherValue) {
          _this.fireChangeEvent(_this.inputTarget.value, _this.inputTarget.value, null);
        } else {
          _this.clear();
        }
      }
      _this.close();
    });
    _defineProperty(_this, "onResultsClick", function (event) {
      if (!(event.target instanceof Element)) return;
      var selected = event.target.closest(optionSelector);
      if (selected) _this.commit(selected);
    });
    _defineProperty(_this, "onResultsMouseDown", function () {
      _this.mouseDown = true;
      _this.resultsTarget.addEventListener("mouseup", function () {
        _this.mouseDown = false;
      }, {
        once: true
      });
    });
    _defineProperty(_this, "onInputChange", function () {
      if (_this.hasHiddenTarget) _this.hiddenTarget.value = "";
      var query = _this.inputTarget.value.trim();
      if (query && query.length >= _this.minLengthValue || _this.hasDataListTarget) {
        _this.fetchResults(query);
      } else {
        _this.hideAndRemoveOptions();
      }
    });
    _defineProperty(_this, "fetchResults", /*#__PURE__*/function () {
      var _ref = _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee(query) {
        var allItems, _iterator, _step, item, itemHtml, url, html;
        return _regeneratorRuntime().wrap(function _callee$(_context) {
          while (1) switch (_context.prev = _context.next) {
            case 0:
              if (_this.hasUrlValue) {
                _context.next = 12;
                break;
              }
              if (_this.hasDataListTarget) {
                _context.next = 5;
                break;
              }
              throw new Error("You must provide a URL or a DataList target");
            case 5:
              _this.resultsTarget.innerHTML = null;
              allItems = _this.dataListTarget.querySelectorAll("li");
              _iterator = _createForOfIteratorHelper(allItems);
              try {
                for (_iterator.s(); !(_step = _iterator.n()).done;) {
                  item = _step.value;
                  if (item.textContent.toLowerCase().includes(query.toLowerCase())) {
                    itemHtml = item.cloneNode(true);
                    itemHtml.setAttribute("role", "option");
                    itemHtml.setAttribute("aria-selected", "false");
                    //add a span around matching string to highlight it
                    itemHtml.innerHTML = itemHtml.innerHTML.replace(new RegExp(query, 'gi'), function (match) {
                      return "<span class=\"text-primary\">".concat(match, "</span>");
                    });
                    _this.resultsTarget.appendChild(itemHtml);
                  }
                }
              } catch (err) {
                _iterator.e(err);
              } finally {
                _iterator.f();
              }
              _this.identifyOptions();
              _this.open();
              return _context.abrupt("return");
            case 12:
              url = _this.buildURL(query);
              _context.prev = 13;
              _this.element.dispatchEvent(new CustomEvent("loadstart"));
              _context.next = 17;
              return _this.doFetch(url);
            case 17:
              html = _context.sent;
              _this.replaceResults(html);
              _this.element.dispatchEvent(new CustomEvent("load"));
              _this.element.dispatchEvent(new CustomEvent("loadend"));
              _context.next = 28;
              break;
            case 23:
              _context.prev = 23;
              _context.t0 = _context["catch"](13);
              _this.element.dispatchEvent(new CustomEvent("error"));
              _this.element.dispatchEvent(new CustomEvent("loadend"));
              throw _context.t0;
            case 28:
            case "end":
              return _context.stop();
          }
        }, _callee, null, [[13, 23]]);
      }));
      return function (_x) {
        return _ref.apply(this, arguments);
      };
    }());
    _defineProperty(_this, "doFetch", /*#__PURE__*/function () {
      var _ref2 = _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee2(url) {
        var response, html;
        return _regeneratorRuntime().wrap(function _callee2$(_context2) {
          while (1) switch (_context2.prev = _context2.next) {
            case 0:
              _context2.next = 2;
              return fetch(url, _this.optionsForFetch());
            case 2:
              response = _context2.sent;
              if (response.ok) {
                _context2.next = 5;
                break;
              }
              throw new Error("Server responded with status ".concat(response.status));
            case 5:
              _context2.next = 7;
              return response.text();
            case 7:
              html = _context2.sent;
              return _context2.abrupt("return", html);
            case 9:
            case "end":
              return _context2.stop();
          }
        }, _callee2);
      }));
      return function (_x2) {
        return _ref2.apply(this, arguments);
      };
    }());
    return _this;
  }
  _inherits(AutoComplete, _Controller);
  return _createClass(AutoComplete, [{
    key: "value",
    get:
    // Getter for the value property
    function get() {
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
    ,
    set: function set(newValue) {
      //check if the new value is an object with a "value" property and "text" property
      if (_typeof(newValue) === "object" && newValue.hasOwnProperty("value") && newValue.hasOwnProperty("text")) {
        this.inputTarget.value = newValue.text;
        this.hiddenTarget.value = newValue.value;
        return;
      }
      //if the value matches an option set the input value to the option text
      if (newValue != "" && newValue != null) {
        var option = this.resultsTarget.querySelector("[data-ac-value='".concat(newValue, "']"));
        if (!option) {
          if (this.hasDataListTarget) {
            option = this.dataListTarget.querySelector("[data-ac-value='".concat(newValue, "']"));
          }
        }
        if (option) {
          this.inputTarget.value = option.textContent;
          this.hiddenTarget.value = newValue;
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
  }, {
    key: "disabled",
    get: function get() {
      return this.inputTarget.disabled;
    },
    set: function set(newValue) {
      this.inputTarget.disabled = newValue;
      this.hiddenTarget.disabled = newValue;
    }
  }, {
    key: "hidden",
    get: function get() {
      return this.element.hidden;
    },
    set: function set(newValue) {
      this.element.hidden = newValue;
    }
  }, {
    key: "connect",
    value: function connect() {
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
  }, {
    key: "shimElement",
    value: function shimElement() {
      var _this2 = this;
      Object.defineProperty(this.element, 'value', {
        get: function get() {
          return _this2.value;
        },
        set: function set(newValue) {
          _this2.value = newValue;
        }
      });
      this.element.focus = function () {
        _this2.inputTarget.focus();
      };
      var proto = this.element;
      while (proto && !Object.getOwnPropertyDescriptor(proto, 'hidden')) {
        proto = Object.getPrototypeOf(proto);
      }
      if (proto) {
        this.baseHidden = Object.getOwnPropertyDescriptor(proto, 'hidden');
        Object.defineProperty(this.element, 'hidden', {
          get: function get() {
            return _this2.baseHidden.get.call(_this2.element);
          },
          set: function set(newValue) {
            _this2.baseHidden.set.call(_this2.element, newValue);
            if (newValue) {
              _this2.hiddenTarget.disabled = true;
              _this2.inputTarget.disabled = true;
              _this2.close();
            } else {
              _this2.hiddenTarget.disabled = false;
              _this2.inputTarget.disabled = false;
            }
          }
        });
      }
      Object.defineProperty(this.element, 'disabled', {
        get: function get() {
          return _this2.disabled;
        },
        set: function set(newValue) {
          _this2.disabled = newValue;
        }
      });
    }
  }, {
    key: "disconnect",
    value: function disconnect() {
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
  }, {
    key: "sibling",
    value: function sibling(next) {
      var options = this.options;
      var selected = this.selectedOption;
      var index = options.indexOf(selected);
      var sibling = next ? options[index + 1] : options[index - 1];
      var def = next ? options[0] : options[options.length - 1];
      return sibling || def;
    }
  }, {
    key: "select",
    value: function select(target) {
      var _target$classList;
      var previouslySelected = this.selectedOption;
      if (previouslySelected) {
        var _previouslySelected$c;
        previouslySelected.removeAttribute("aria-selected");
        (_previouslySelected$c = previouslySelected.classList).remove.apply(_previouslySelected$c, _toConsumableArray(this.selectedClassesOrDefault));
      }
      target.setAttribute("aria-selected", "true");
      (_target$classList = target.classList).add.apply(_target$classList, _toConsumableArray(this.selectedClassesOrDefault));
      this.inputTarget.setAttribute("aria-activedescendant", target.id);
      target.scrollIntoView({
        behavior: "auto",
        block: "nearest"
      });
    }
  }, {
    key: "commit",
    value: function commit(selected) {
      if (selected.getAttribute("aria-disabled") === "true") return;
      if (selected instanceof HTMLAnchorElement) {
        selected.click();
        this.close();
        return;
      }
      var textValue = selected.getAttribute("data-ac-label") || selected.textContent.trim();
      var value = selected.getAttribute("data-ac-value") || textValue;
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
  }, {
    key: "fireChangeEvent",
    value: function fireChangeEvent(value, textValue, selected) {
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
  }, {
    key: "clear",
    value: function clear() {
      this.inputTarget.value = "";
      if (this.hasHiddenTarget) this.hiddenTarget.value = "";
    }
  }, {
    key: "identifyOptions",
    value: function identifyOptions() {
      var prefix = this.resultsTarget.id || "stimulus-autocomplete";
      var optionsWithoutId = this.resultsTarget.querySelectorAll("".concat(optionSelector, ":not([id])"));
      optionsWithoutId.forEach(function (el) {
        return el.id = "".concat(prefix, "-option-").concat(AutoComplete.uniqOptionId++);
      });
    }
  }, {
    key: "hideAndRemoveOptions",
    value: function hideAndRemoveOptions() {
      this.close();
      this.resultsTarget.innerHTML = null;
    }
  }, {
    key: "buildURL",
    value: function buildURL(query) {
      var url = new URL(this.urlValue, window.location.href);
      var params = new URLSearchParams(url.search.slice(1));
      params.append(this.queryParamValue, query);
      url.search = params.toString();
      return url.toString();
    }
  }, {
    key: "replaceResults",
    value: function replaceResults(html) {
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
  }, {
    key: "open",
    value: function open() {
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
  }, {
    key: "close",
    value: function close() {
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
  }, {
    key: "resultsShown",
    get: function get() {
      return !this.resultsTarget.hidden;
    },
    set: function set(value) {
      this.resultsTarget.hidden = !value;
    }
  }, {
    key: "options",
    get: function get() {
      return Array.from(this.resultsTarget.querySelectorAll(optionSelector));
    }
  }, {
    key: "selectedOption",
    get: function get() {
      return this.resultsTarget.querySelector(activeSelector);
    }
  }, {
    key: "selectedClassesOrDefault",
    get: function get() {
      return this.hasSelectedClass ? this.selectedClasses : ["active"];
    }
  }, {
    key: "optionsForFetch",
    value: function optionsForFetch() {
      return {
        headers: {
          "X-Requested-With": "XMLHttpRequest"
        }
      }; // override if you need
    }
  }]);
}(_hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller);
_defineProperty(AutoComplete, "targets", ["input", "hidden", "results", "dataList"]);
_defineProperty(AutoComplete, "classes", ["selected"]);
_defineProperty(AutoComplete, "values", {
  ready: Boolean,
  submitOnEnter: Boolean,
  url: String,
  minLength: Number,
  allowOther: Boolean,
  required: Boolean,
  delay: {
    type: Number,
    "default": 300
  },
  queryParam: {
    type: String,
    "default": "q"
  }
});
_defineProperty(AutoComplete, "uniqOptionId", 0);
var debounce = function debounce(fn) {
  var delay = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 10;
  var timeoutId = null;
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
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _classCallCheck(a, n) { if (!(a instanceof n)) throw new TypeError("Cannot call a class as a function"); }
function _defineProperties(e, r) { for (var t = 0; t < r.length; t++) { var o = r[t]; o.enumerable = o.enumerable || !1, o.configurable = !0, "value" in o && (o.writable = !0), Object.defineProperty(e, _toPropertyKey(o.key), o); } }
function _createClass(e, r, t) { return r && _defineProperties(e.prototype, r), t && _defineProperties(e, t), Object.defineProperty(e, "prototype", { writable: !1 }), e; }
function _callSuper(t, o, e) { return o = _getPrototypeOf(o), _possibleConstructorReturn(t, _isNativeReflectConstruct() ? Reflect.construct(o, e || [], _getPrototypeOf(t).constructor) : o.apply(t, e)); }
function _possibleConstructorReturn(t, e) { if (e && ("object" == _typeof(e) || "function" == typeof e)) return e; if (void 0 !== e) throw new TypeError("Derived constructors may only return object or undefined"); return _assertThisInitialized(t); }
function _assertThisInitialized(e) { if (void 0 === e) throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); return e; }
function _isNativeReflectConstruct() { try { var t = !Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], function () {})); } catch (t) {} return (_isNativeReflectConstruct = function _isNativeReflectConstruct() { return !!t; })(); }
function _getPrototypeOf(t) { return _getPrototypeOf = Object.setPrototypeOf ? Object.getPrototypeOf.bind() : function (t) { return t.__proto__ || Object.getPrototypeOf(t); }, _getPrototypeOf(t); }
function _inherits(t, e) { if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function"); t.prototype = Object.create(e && e.prototype, { constructor: { value: t, writable: !0, configurable: !0 } }), Object.defineProperty(t, "prototype", { writable: !1 }), e && _setPrototypeOf(t, e); }
function _setPrototypeOf(t, e) { return _setPrototypeOf = Object.setPrototypeOf ? Object.setPrototypeOf.bind() : function (t, e) { return t.__proto__ = e, t; }, _setPrototypeOf(t, e); }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }

var DetailTabsController = /*#__PURE__*/function (_Controller) {
  function DetailTabsController() {
    var _this;
    _classCallCheck(this, DetailTabsController);
    for (var _len = arguments.length, args = new Array(_len), _key = 0; _key < _len; _key++) {
      args[_key] = arguments[_key];
    }
    _this = _callSuper(this, DetailTabsController, [].concat(args));
    _defineProperty(_this, "foundFirst", false);
    return _this;
  }
  _inherits(DetailTabsController, _Controller);
  return _createClass(DetailTabsController, [{
    key: "tabBtnTargetConnected",
    value: function tabBtnTargetConnected(event) {
      var tab = event.id.replace('nav-', '').replace('-tab', '');
      var urlTab = KMP_utils.urlParam('tab');
      if (urlTab) {
        if (tab == urlTab) {
          event.click();
          this.foundFirst = true;
        }
      } else {
        if (!this.foundFirst) {
          this.tabBtnTargets[0].click();
        }
      }
      event.addEventListener('click', this.tabBtnClicked.bind(this));
    }
  }, {
    key: "tabBtnClicked",
    value: function tabBtnClicked(event) {
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
  }, {
    key: "tabBtnTargetDisconnected",
    value: function tabBtnTargetDisconnected(event) {
      event.removeEventListener('click', this.tabBtnClicked.bind(this));
    }
  }]);
}(_hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller);
_defineProperty(DetailTabsController, "targets", ["tabBtn", "tabContent"]);
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["detail-tabs"] = DetailTabsController;

/***/ }),

/***/ "./assets/js/controllers/image-preview-controller.js":
/*!***********************************************************!*\
  !*** ./assets/js/controllers/image-preview-controller.js ***!
  \***********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _classCallCheck(a, n) { if (!(a instanceof n)) throw new TypeError("Cannot call a class as a function"); }
function _defineProperties(e, r) { for (var t = 0; t < r.length; t++) { var o = r[t]; o.enumerable = o.enumerable || !1, o.configurable = !0, "value" in o && (o.writable = !0), Object.defineProperty(e, _toPropertyKey(o.key), o); } }
function _createClass(e, r, t) { return r && _defineProperties(e.prototype, r), t && _defineProperties(e, t), Object.defineProperty(e, "prototype", { writable: !1 }), e; }
function _callSuper(t, o, e) { return o = _getPrototypeOf(o), _possibleConstructorReturn(t, _isNativeReflectConstruct() ? Reflect.construct(o, e || [], _getPrototypeOf(t).constructor) : o.apply(t, e)); }
function _possibleConstructorReturn(t, e) { if (e && ("object" == _typeof(e) || "function" == typeof e)) return e; if (void 0 !== e) throw new TypeError("Derived constructors may only return object or undefined"); return _assertThisInitialized(t); }
function _assertThisInitialized(e) { if (void 0 === e) throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); return e; }
function _isNativeReflectConstruct() { try { var t = !Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], function () {})); } catch (t) {} return (_isNativeReflectConstruct = function _isNativeReflectConstruct() { return !!t; })(); }
function _getPrototypeOf(t) { return _getPrototypeOf = Object.setPrototypeOf ? Object.getPrototypeOf.bind() : function (t) { return t.__proto__ || Object.getPrototypeOf(t); }, _getPrototypeOf(t); }
function _inherits(t, e) { if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function"); t.prototype = Object.create(e && e.prototype, { constructor: { value: t, writable: !0, configurable: !0 } }), Object.defineProperty(t, "prototype", { writable: !1 }), e && _setPrototypeOf(t, e); }
function _setPrototypeOf(t, e) { return _setPrototypeOf = Object.setPrototypeOf ? Object.setPrototypeOf.bind() : function (t, e) { return t.__proto__ = e, t; }, _setPrototypeOf(t, e); }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }

var ImagePreview = /*#__PURE__*/function (_Controller) {
  function ImagePreview() {
    _classCallCheck(this, ImagePreview);
    return _callSuper(this, ImagePreview, arguments);
  }
  _inherits(ImagePreview, _Controller);
  return _createClass(ImagePreview, [{
    key: "preview",
    value: function preview(event) {
      if (event.target.files.length > 0) {
        var src = URL.createObjectURL(event.target.files[0]);
        this.previewTarget.src = src;
        this.loadingTarget.classList.add("d-none");
        this.previewTarget.hidden = false;
      }
    }
  }]);
}(_hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller);
_defineProperty(ImagePreview, "targets", ['file', 'preview', 'loading']);
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["image-preview"] = ImagePreview;

/***/ }),

/***/ "./assets/js/controllers/member-mobile-card-profile-controller.js":
/*!************************************************************************!*\
  !*** ./assets/js/controllers/member-mobile-card-profile-controller.js ***!
  \************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");
/* provided dependency */ var $ = __webpack_require__(/*! jquery */ "./node_modules/jquery/dist/jquery.js");
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _classCallCheck(a, n) { if (!(a instanceof n)) throw new TypeError("Cannot call a class as a function"); }
function _defineProperties(e, r) { for (var t = 0; t < r.length; t++) { var o = r[t]; o.enumerable = o.enumerable || !1, o.configurable = !0, "value" in o && (o.writable = !0), Object.defineProperty(e, _toPropertyKey(o.key), o); } }
function _createClass(e, r, t) { return r && _defineProperties(e.prototype, r), t && _defineProperties(e, t), Object.defineProperty(e, "prototype", { writable: !1 }), e; }
function _callSuper(t, o, e) { return o = _getPrototypeOf(o), _possibleConstructorReturn(t, _isNativeReflectConstruct() ? Reflect.construct(o, e || [], _getPrototypeOf(t).constructor) : o.apply(t, e)); }
function _possibleConstructorReturn(t, e) { if (e && ("object" == _typeof(e) || "function" == typeof e)) return e; if (void 0 !== e) throw new TypeError("Derived constructors may only return object or undefined"); return _assertThisInitialized(t); }
function _assertThisInitialized(e) { if (void 0 === e) throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); return e; }
function _isNativeReflectConstruct() { try { var t = !Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], function () {})); } catch (t) {} return (_isNativeReflectConstruct = function _isNativeReflectConstruct() { return !!t; })(); }
function _getPrototypeOf(t) { return _getPrototypeOf = Object.setPrototypeOf ? Object.getPrototypeOf.bind() : function (t) { return t.__proto__ || Object.getPrototypeOf(t); }, _getPrototypeOf(t); }
function _inherits(t, e) { if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function"); t.prototype = Object.create(e && e.prototype, { constructor: { value: t, writable: !0, configurable: !0 } }), Object.defineProperty(t, "prototype", { writable: !1 }), e && _setPrototypeOf(t, e); }
function _setPrototypeOf(t, e) { return _setPrototypeOf = Object.setPrototypeOf ? Object.setPrototypeOf.bind() : function (t, e) { return t.__proto__ = e, t; }, _setPrototypeOf(t, e); }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }

var MemberMobileCardProfile = /*#__PURE__*/function (_Controller) {
  function MemberMobileCardProfile() {
    _classCallCheck(this, MemberMobileCardProfile);
    return _callSuper(this, MemberMobileCardProfile, arguments);
  }
  _inherits(MemberMobileCardProfile, _Controller);
  return _createClass(MemberMobileCardProfile, [{
    key: "initialize",
    value: function initialize() {
      this.currentCard = null;
      this.cardCount = 0;
    }
  }, {
    key: "startCard",
    value: function startCard(title) {
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
  }, {
    key: "loadCard",
    value: function loadCard() {
      var _this = this;
      this.cardSetTarget.innerHTML = "";
      this.loadingTarget.hidden = false;
      this.memberDetailsTarget.hidden = true;
      fetch(this.urlValue).then(function (response) {
        return response.json();
      }).then(function (data) {
        _this.loadingTarget.hidden = true;
        _this.memberDetailsTarget.hidden = false;
        _this.nameTarget.textContent = data.member.first_name + ' ' + data.member.last_name;
        _this.scaNameTarget.textContent = data.member.sca_name;
        _this.branchNameTarget.textContent = data.member.branch.name;
        if (data.member.membership_number && data.member.membership_number.length > 0) {
          var memberExpDate = new Date(data.member.membership_expires_on);
          if (memberExpDate < new Date()) {
            memberExpDate = "Expired";
          } else {
            memberExpDate = " - " + memberExpDate.toLocaleDateString();
          }
          _this.membershipInfoTarget.textContent = data.member.membership_number + ' ' + memberExpDate;
        } else {
          _this.membershipInfoTarget.textContent = "No Membership Info";
        }
        if (data.member.background_check_expires_on) {
          var backgroundCheckExpDate = new Date(data.member.background_check_expires_on);
          if (backgroundCheckExpDate < new Date()) {
            backgroundCheckExpDate = "Expired";
          } else {
            backgroundCheckExpDate = 'Current' + backgroundCheckExpDate.toLocaleDateString();
          }
          _this.backgroundCheckTarget.textContent = backgroundCheckExpDate;
        } else {
          _this.backgroundCheckTarget.textContent = "Not on file";
        }
        _this.lastUpdateTarget.textContent = new Date().toLocaleString();
        for (var key in data) {
          if (key === 'member') {
            continue;
          }
          var pluginData = data[key];
          for (var sectionKey in pluginData) {
            var sectionData = pluginData[sectionKey];
            var keysCount = Object.keys(sectionData).length;
            if (keysCount > 0) {
              _this.startCard(sectionKey);
            } else {
              continue;
            }
            var groupTable = document.createElement("table");
            groupTable.classList.add("table", "card-body-table");
            var groupTableBody = document.createElement("tbody");
            groupTable.appendChild(groupTableBody);
            for (var groupKey in sectionData) {
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
              for (var i = 0; i < groupData.length; i++) {
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
                  var itemValueCol = $("<td>", {
                    "class": "col-6 " + textAlignClass,
                    colspan: colspan
                  }).text(itemData);
                  groupRow.append(itemValueCol);
                  colCount++;
                }
              }
              groupTableBody.append(groupRow);
            }
            _this.currentCard.appendChild(groupTable);
          }
        }
      });
    }
  }, {
    key: "connect",
    value: function connect() {
      console.log("MemberMobileCardProfile connected");
      //this.loadCard();
    }
  }]);
}(_hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller);
_defineProperty(MemberMobileCardProfile, "targets", ["cardSet", "name", "scaName", "branchName", "membershipInfo", "backgroundCheck", "lastUpdate", "loading", "memberDetails"]);
_defineProperty(MemberMobileCardProfile, "values", {
  url: String
});
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["member-mobile-card-profile"] = MemberMobileCardProfile;

/***/ }),

/***/ "./assets/js/controllers/member-mobile-card-pwa-controller.js":
/*!********************************************************************!*\
  !*** ./assets/js/controllers/member-mobile-card-pwa-controller.js ***!
  \********************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _classCallCheck(a, n) { if (!(a instanceof n)) throw new TypeError("Cannot call a class as a function"); }
function _defineProperties(e, r) { for (var t = 0; t < r.length; t++) { var o = r[t]; o.enumerable = o.enumerable || !1, o.configurable = !0, "value" in o && (o.writable = !0), Object.defineProperty(e, _toPropertyKey(o.key), o); } }
function _createClass(e, r, t) { return r && _defineProperties(e.prototype, r), t && _defineProperties(e, t), Object.defineProperty(e, "prototype", { writable: !1 }), e; }
function _callSuper(t, o, e) { return o = _getPrototypeOf(o), _possibleConstructorReturn(t, _isNativeReflectConstruct() ? Reflect.construct(o, e || [], _getPrototypeOf(t).constructor) : o.apply(t, e)); }
function _possibleConstructorReturn(t, e) { if (e && ("object" == _typeof(e) || "function" == typeof e)) return e; if (void 0 !== e) throw new TypeError("Derived constructors may only return object or undefined"); return _assertThisInitialized(t); }
function _assertThisInitialized(e) { if (void 0 === e) throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); return e; }
function _isNativeReflectConstruct() { try { var t = !Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], function () {})); } catch (t) {} return (_isNativeReflectConstruct = function _isNativeReflectConstruct() { return !!t; })(); }
function _getPrototypeOf(t) { return _getPrototypeOf = Object.setPrototypeOf ? Object.getPrototypeOf.bind() : function (t) { return t.__proto__ || Object.getPrototypeOf(t); }, _getPrototypeOf(t); }
function _inherits(t, e) { if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function"); t.prototype = Object.create(e && e.prototype, { constructor: { value: t, writable: !0, configurable: !0 } }), Object.defineProperty(t, "prototype", { writable: !1 }), e && _setPrototypeOf(t, e); }
function _setPrototypeOf(t, e) { return _setPrototypeOf = Object.setPrototypeOf ? Object.setPrototypeOf.bind() : function (t, e) { return t.__proto__ = e, t; }, _setPrototypeOf(t, e); }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }

var MemberMobileCardPWA = /*#__PURE__*/function (_Controller) {
  function MemberMobileCardPWA() {
    _classCallCheck(this, MemberMobileCardPWA);
    return _callSuper(this, MemberMobileCardPWA, arguments);
  }
  _inherits(MemberMobileCardPWA, _Controller);
  return _createClass(MemberMobileCardPWA, [{
    key: "urlCacheTargetConnected",
    value: function urlCacheTargetConnected() {
      this.urlCacheValue = JSON.parse(this.urlCacheTarget.textContent);
    }
  }, {
    key: "updateOnlineStatus",
    value: function updateOnlineStatus() {
      var statusDiv = this.statusTarget;
      var refreshButton = this.refreshBtnTarget;
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
  }, {
    key: "manageOnlineStatus",
    value: function manageOnlineStatus() {
      var _this = this;
      this.updateOnlineStatus();
      window.addEventListener('online', this.updateOnlineStatus.bind(this));
      window.addEventListener('offline', this.updateOnlineStatus.bind(this));
      navigator.serviceWorker.register(this.swUrlValue).then(function (registration) {
        console.log('Service Worker registered with scope:', registration.scope);
        registration.active.postMessage({
          type: 'CACHE_URLS',
          payload: _this.urlCacheValue
        });
      }, function (error) {
        console.log('Service Worker registration failed:', error);
      });
    }
  }, {
    key: "refreshPageIfOnline",
    value: function refreshPageIfOnline() {
      if (navigator.onLine) {
        window.location.reload();
      }
    }
  }, {
    key: "connect",
    value: function connect() {
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', this.manageOnlineStatus.bind(this));
      }
      setInterval(this.refreshPageIfOnline, 300000);
    }
  }, {
    key: "disconnect",
    value: function disconnect() {
      window.addEventListener('load', this.manageOnlineStatus.bind(this));
      window.removeEventListener('online', this.updateOnlineStatus.bind(this));
      window.removeEventListener('offline', this.updateOnlineStatus.bind(this));
    }
  }]);
}(_hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller);
_defineProperty(MemberMobileCardPWA, "targets", ["urlCache", "status", "refreshBtn"]);
_defineProperty(MemberMobileCardPWA, "values", {
  swUrl: String
});
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["member-mobile-card-pwa"] = MemberMobileCardPWA;

/***/ }),

/***/ "./assets/js/controllers/member-unique-email-controller.js":
/*!*****************************************************************!*\
  !*** ./assets/js/controllers/member-unique-email-controller.js ***!
  \*****************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _classCallCheck(a, n) { if (!(a instanceof n)) throw new TypeError("Cannot call a class as a function"); }
function _defineProperties(e, r) { for (var t = 0; t < r.length; t++) { var o = r[t]; o.enumerable = o.enumerable || !1, o.configurable = !0, "value" in o && (o.writable = !0), Object.defineProperty(e, _toPropertyKey(o.key), o); } }
function _createClass(e, r, t) { return r && _defineProperties(e.prototype, r), t && _defineProperties(e, t), Object.defineProperty(e, "prototype", { writable: !1 }), e; }
function _callSuper(t, o, e) { return o = _getPrototypeOf(o), _possibleConstructorReturn(t, _isNativeReflectConstruct() ? Reflect.construct(o, e || [], _getPrototypeOf(t).constructor) : o.apply(t, e)); }
function _possibleConstructorReturn(t, e) { if (e && ("object" == _typeof(e) || "function" == typeof e)) return e; if (void 0 !== e) throw new TypeError("Derived constructors may only return object or undefined"); return _assertThisInitialized(t); }
function _assertThisInitialized(e) { if (void 0 === e) throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); return e; }
function _isNativeReflectConstruct() { try { var t = !Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], function () {})); } catch (t) {} return (_isNativeReflectConstruct = function _isNativeReflectConstruct() { return !!t; })(); }
function _getPrototypeOf(t) { return _getPrototypeOf = Object.setPrototypeOf ? Object.getPrototypeOf.bind() : function (t) { return t.__proto__ || Object.getPrototypeOf(t); }, _getPrototypeOf(t); }
function _inherits(t, e) { if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function"); t.prototype = Object.create(e && e.prototype, { constructor: { value: t, writable: !0, configurable: !0 } }), Object.defineProperty(t, "prototype", { writable: !1 }), e && _setPrototypeOf(t, e); }
function _setPrototypeOf(t, e) { return _setPrototypeOf = Object.setPrototypeOf ? Object.setPrototypeOf.bind() : function (t, e) { return t.__proto__ = e, t; }, _setPrototypeOf(t, e); }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }

var MemberUniqueEmail = /*#__PURE__*/function (_Controller) {
  function MemberUniqueEmail() {
    _classCallCheck(this, MemberUniqueEmail);
    return _callSuper(this, MemberUniqueEmail, arguments);
  }
  _inherits(MemberUniqueEmail, _Controller);
  return _createClass(MemberUniqueEmail, [{
    key: "connect",
    value: function connect() {
      console.log("MemberUniqueEmail connected");
      this.element.removeAttribute('oninput');
      this.element.removeAttribute('oninvalid');
      this.element.addEventListener('change', this.checkEmail.bind(this));
    }
  }, {
    key: "disconnect",
    value: function disconnect(event) {
      this.element.removeEventListener('change', this.checkEmail.bind(this));
    }
  }, {
    key: "checkEmail",
    value: function checkEmail(event) {
      var _this = this;
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
      fetch(checkEmailUrl).then(function (response) {
        return response.json();
      }).then(function (data) {
        if (data) {
          _this.element.classList.add('is-invalid');
          _this.element.classList.remove('is-valid');
          _this.element.setCustomValidity('This email address is already taken.');
        } else {
          _this.element.classList.add('is-valid');
          _this.element.classList.remove('is-invalid');
          _this.element.setCustomValidity('');
        }
      });
    }
  }]);
}(_hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller);
_defineProperty(MemberUniqueEmail, "values", {
  url: String
});
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["member-unique-email"] = MemberUniqueEmail;

/***/ }),

/***/ "./assets/js/controllers/member-verify-form-controller.js":
/*!****************************************************************!*\
  !*** ./assets/js/controllers/member-verify-form-controller.js ***!
  \****************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _classCallCheck(a, n) { if (!(a instanceof n)) throw new TypeError("Cannot call a class as a function"); }
function _defineProperties(e, r) { for (var t = 0; t < r.length; t++) { var o = r[t]; o.enumerable = o.enumerable || !1, o.configurable = !0, "value" in o && (o.writable = !0), Object.defineProperty(e, _toPropertyKey(o.key), o); } }
function _createClass(e, r, t) { return r && _defineProperties(e.prototype, r), t && _defineProperties(e, t), Object.defineProperty(e, "prototype", { writable: !1 }), e; }
function _callSuper(t, o, e) { return o = _getPrototypeOf(o), _possibleConstructorReturn(t, _isNativeReflectConstruct() ? Reflect.construct(o, e || [], _getPrototypeOf(t).constructor) : o.apply(t, e)); }
function _possibleConstructorReturn(t, e) { if (e && ("object" == _typeof(e) || "function" == typeof e)) return e; if (void 0 !== e) throw new TypeError("Derived constructors may only return object or undefined"); return _assertThisInitialized(t); }
function _assertThisInitialized(e) { if (void 0 === e) throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); return e; }
function _isNativeReflectConstruct() { try { var t = !Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], function () {})); } catch (t) {} return (_isNativeReflectConstruct = function _isNativeReflectConstruct() { return !!t; })(); }
function _getPrototypeOf(t) { return _getPrototypeOf = Object.setPrototypeOf ? Object.getPrototypeOf.bind() : function (t) { return t.__proto__ || Object.getPrototypeOf(t); }, _getPrototypeOf(t); }
function _inherits(t, e) { if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function"); t.prototype = Object.create(e && e.prototype, { constructor: { value: t, writable: !0, configurable: !0 } }), Object.defineProperty(t, "prototype", { writable: !1 }), e && _setPrototypeOf(t, e); }
function _setPrototypeOf(t, e) { return _setPrototypeOf = Object.setPrototypeOf ? Object.setPrototypeOf.bind() : function (t, e) { return t.__proto__ = e, t; }, _setPrototypeOf(t, e); }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }

var MemberVerifyForm = /*#__PURE__*/function (_Controller) {
  function MemberVerifyForm() {
    _classCallCheck(this, MemberVerifyForm);
    return _callSuper(this, MemberVerifyForm, arguments);
  }
  _inherits(MemberVerifyForm, _Controller);
  return _createClass(MemberVerifyForm, [{
    key: "toggleParent",
    value: function toggleParent(event) {
      var checked = event.target.checked;
      this.scaMemberTarget.disabled = !checked;
    }
  }, {
    key: "toggleMembership",
    value: function toggleMembership(event) {
      var checked = event.target.checked;
      this.membershipNumberTarget.disabled = !checked;
      this.membershipExpDateTarget.disabled = !checked;
    }
  }]);
}(_hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller);
_defineProperty(MemberVerifyForm, "targets", ['scaMember', 'membershipNumber', 'membershipExpDate']);
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["member-verify-form"] = MemberVerifyForm;

/***/ }),

/***/ "./assets/js/controllers/modal-opener-controller.js":
/*!**********************************************************!*\
  !*** ./assets/js/controllers/modal-opener-controller.js ***!
  \**********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _classCallCheck(a, n) { if (!(a instanceof n)) throw new TypeError("Cannot call a class as a function"); }
function _defineProperties(e, r) { for (var t = 0; t < r.length; t++) { var o = r[t]; o.enumerable = o.enumerable || !1, o.configurable = !0, "value" in o && (o.writable = !0), Object.defineProperty(e, _toPropertyKey(o.key), o); } }
function _createClass(e, r, t) { return r && _defineProperties(e.prototype, r), t && _defineProperties(e, t), Object.defineProperty(e, "prototype", { writable: !1 }), e; }
function _callSuper(t, o, e) { return o = _getPrototypeOf(o), _possibleConstructorReturn(t, _isNativeReflectConstruct() ? Reflect.construct(o, e || [], _getPrototypeOf(t).constructor) : o.apply(t, e)); }
function _possibleConstructorReturn(t, e) { if (e && ("object" == _typeof(e) || "function" == typeof e)) return e; if (void 0 !== e) throw new TypeError("Derived constructors may only return object or undefined"); return _assertThisInitialized(t); }
function _assertThisInitialized(e) { if (void 0 === e) throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); return e; }
function _isNativeReflectConstruct() { try { var t = !Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], function () {})); } catch (t) {} return (_isNativeReflectConstruct = function _isNativeReflectConstruct() { return !!t; })(); }
function _getPrototypeOf(t) { return _getPrototypeOf = Object.setPrototypeOf ? Object.getPrototypeOf.bind() : function (t) { return t.__proto__ || Object.getPrototypeOf(t); }, _getPrototypeOf(t); }
function _inherits(t, e) { if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function"); t.prototype = Object.create(e && e.prototype, { constructor: { value: t, writable: !0, configurable: !0 } }), Object.defineProperty(t, "prototype", { writable: !1 }), e && _setPrototypeOf(t, e); }
function _setPrototypeOf(t, e) { return _setPrototypeOf = Object.setPrototypeOf ? Object.setPrototypeOf.bind() : function (t, e) { return t.__proto__ = e, t; }, _setPrototypeOf(t, e); }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }

var ModalOpener = /*#__PURE__*/function (_Controller) {
  function ModalOpener() {
    _classCallCheck(this, ModalOpener);
    return _callSuper(this, ModalOpener, arguments);
  }
  _inherits(ModalOpener, _Controller);
  return _createClass(ModalOpener, [{
    key: "modalBtnValueChanged",
    value: function modalBtnValueChanged() {
      var modal = document.getElementById(this.modalBtnValue);
      modal.click();
    }
  }]);
}(_hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller);
_defineProperty(ModalOpener, "values", {
  modalBtn: String
});
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["modal-opener"] = ModalOpener;

/***/ }),

/***/ "./assets/js/controllers/nav-bar-controller.js":
/*!*****************************************************!*\
  !*** ./assets/js/controllers/nav-bar-controller.js ***!
  \*****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _classCallCheck(a, n) { if (!(a instanceof n)) throw new TypeError("Cannot call a class as a function"); }
function _defineProperties(e, r) { for (var t = 0; t < r.length; t++) { var o = r[t]; o.enumerable = o.enumerable || !1, o.configurable = !0, "value" in o && (o.writable = !0), Object.defineProperty(e, _toPropertyKey(o.key), o); } }
function _createClass(e, r, t) { return r && _defineProperties(e.prototype, r), t && _defineProperties(e, t), Object.defineProperty(e, "prototype", { writable: !1 }), e; }
function _callSuper(t, o, e) { return o = _getPrototypeOf(o), _possibleConstructorReturn(t, _isNativeReflectConstruct() ? Reflect.construct(o, e || [], _getPrototypeOf(t).constructor) : o.apply(t, e)); }
function _possibleConstructorReturn(t, e) { if (e && ("object" == _typeof(e) || "function" == typeof e)) return e; if (void 0 !== e) throw new TypeError("Derived constructors may only return object or undefined"); return _assertThisInitialized(t); }
function _assertThisInitialized(e) { if (void 0 === e) throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); return e; }
function _isNativeReflectConstruct() { try { var t = !Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], function () {})); } catch (t) {} return (_isNativeReflectConstruct = function _isNativeReflectConstruct() { return !!t; })(); }
function _getPrototypeOf(t) { return _getPrototypeOf = Object.setPrototypeOf ? Object.getPrototypeOf.bind() : function (t) { return t.__proto__ || Object.getPrototypeOf(t); }, _getPrototypeOf(t); }
function _inherits(t, e) { if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function"); t.prototype = Object.create(e && e.prototype, { constructor: { value: t, writable: !0, configurable: !0 } }), Object.defineProperty(t, "prototype", { writable: !1 }), e && _setPrototypeOf(t, e); }
function _setPrototypeOf(t, e) { return _setPrototypeOf = Object.setPrototypeOf ? Object.setPrototypeOf.bind() : function (t, e) { return t.__proto__ = e, t; }, _setPrototypeOf(t, e); }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }

var NavBarController = /*#__PURE__*/function (_Controller) {
  function NavBarController() {
    _classCallCheck(this, NavBarController);
    return _callSuper(this, NavBarController, arguments);
  }
  _inherits(NavBarController, _Controller);
  return _createClass(NavBarController, [{
    key: "navHeaderClicked",
    value: function navHeaderClicked(event) {
      var state = event.target.getAttribute('aria-expanded');
      if (state === 'true') {
        var recordExpandUrl = event.target.getAttribute('data-expand-url');
        fetch(recordExpandUrl);
      } else {
        var recordCollapseUrl = event.target.getAttribute('data-collapse-url');
        fetch(recordCollapseUrl);
      }
    }
  }, {
    key: "navHeaderTargetConnected",
    value: function navHeaderTargetConnected(event) {
      event.addEventListener('click', this.navHeaderClicked.bind(this));
    }
  }, {
    key: "navHeaderTargetDisconnected",
    value: function navHeaderTargetDisconnected(event) {
      event.removeEventListener('click', this.navHeaderClicked.bind(this));
    }
  }]);
}(_hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller);
_defineProperty(NavBarController, "targets", ["navHeader"]);
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["nav-bar"] = NavBarController;

/***/ }),

/***/ "./assets/js/controllers/permission-add-role-controller.js":
/*!*****************************************************************!*\
  !*** ./assets/js/controllers/permission-add-role-controller.js ***!
  \*****************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _classCallCheck(a, n) { if (!(a instanceof n)) throw new TypeError("Cannot call a class as a function"); }
function _defineProperties(e, r) { for (var t = 0; t < r.length; t++) { var o = r[t]; o.enumerable = o.enumerable || !1, o.configurable = !0, "value" in o && (o.writable = !0), Object.defineProperty(e, _toPropertyKey(o.key), o); } }
function _createClass(e, r, t) { return r && _defineProperties(e.prototype, r), t && _defineProperties(e, t), Object.defineProperty(e, "prototype", { writable: !1 }), e; }
function _callSuper(t, o, e) { return o = _getPrototypeOf(o), _possibleConstructorReturn(t, _isNativeReflectConstruct() ? Reflect.construct(o, e || [], _getPrototypeOf(t).constructor) : o.apply(t, e)); }
function _possibleConstructorReturn(t, e) { if (e && ("object" == _typeof(e) || "function" == typeof e)) return e; if (void 0 !== e) throw new TypeError("Derived constructors may only return object or undefined"); return _assertThisInitialized(t); }
function _assertThisInitialized(e) { if (void 0 === e) throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); return e; }
function _isNativeReflectConstruct() { try { var t = !Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], function () {})); } catch (t) {} return (_isNativeReflectConstruct = function _isNativeReflectConstruct() { return !!t; })(); }
function _getPrototypeOf(t) { return _getPrototypeOf = Object.setPrototypeOf ? Object.getPrototypeOf.bind() : function (t) { return t.__proto__ || Object.getPrototypeOf(t); }, _getPrototypeOf(t); }
function _inherits(t, e) { if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function"); t.prototype = Object.create(e && e.prototype, { constructor: { value: t, writable: !0, configurable: !0 } }), Object.defineProperty(t, "prototype", { writable: !1 }), e && _setPrototypeOf(t, e); }
function _setPrototypeOf(t, e) { return _setPrototypeOf = Object.setPrototypeOf ? Object.setPrototypeOf.bind() : function (t, e) { return t.__proto__ = e, t; }, _setPrototypeOf(t, e); }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }

var PermissionAddRole = /*#__PURE__*/function (_Controller) {
  function PermissionAddRole() {
    _classCallCheck(this, PermissionAddRole);
    return _callSuper(this, PermissionAddRole, arguments);
  }
  _inherits(PermissionAddRole, _Controller);
  return _createClass(PermissionAddRole, [{
    key: "checkSubmitEnable",
    value: function checkSubmitEnable() {
      var role = this.roleTarget.value;
      var roleId = Number(role.replace(/_/g, ""));
      if (roleId > 0) {
        this.submitBtnTarget.disabled = false;
        this.submitBtnTarget.focus();
      } else {
        this.submitBtnTarget.disabled = true;
      }
    }
  }]);
}(_hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller);
_defineProperty(PermissionAddRole, "targets", ["role", "form", "submitBtn"]);
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["permission-add-role"] = PermissionAddRole;

/***/ }),

/***/ "./assets/js/controllers/role-add-member-controller.js":
/*!*************************************************************!*\
  !*** ./assets/js/controllers/role-add-member-controller.js ***!
  \*************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _classCallCheck(a, n) { if (!(a instanceof n)) throw new TypeError("Cannot call a class as a function"); }
function _defineProperties(e, r) { for (var t = 0; t < r.length; t++) { var o = r[t]; o.enumerable = o.enumerable || !1, o.configurable = !0, "value" in o && (o.writable = !0), Object.defineProperty(e, _toPropertyKey(o.key), o); } }
function _createClass(e, r, t) { return r && _defineProperties(e.prototype, r), t && _defineProperties(e, t), Object.defineProperty(e, "prototype", { writable: !1 }), e; }
function _callSuper(t, o, e) { return o = _getPrototypeOf(o), _possibleConstructorReturn(t, _isNativeReflectConstruct() ? Reflect.construct(o, e || [], _getPrototypeOf(t).constructor) : o.apply(t, e)); }
function _possibleConstructorReturn(t, e) { if (e && ("object" == _typeof(e) || "function" == typeof e)) return e; if (void 0 !== e) throw new TypeError("Derived constructors may only return object or undefined"); return _assertThisInitialized(t); }
function _assertThisInitialized(e) { if (void 0 === e) throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); return e; }
function _isNativeReflectConstruct() { try { var t = !Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], function () {})); } catch (t) {} return (_isNativeReflectConstruct = function _isNativeReflectConstruct() { return !!t; })(); }
function _getPrototypeOf(t) { return _getPrototypeOf = Object.setPrototypeOf ? Object.getPrototypeOf.bind() : function (t) { return t.__proto__ || Object.getPrototypeOf(t); }, _getPrototypeOf(t); }
function _inherits(t, e) { if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function"); t.prototype = Object.create(e && e.prototype, { constructor: { value: t, writable: !0, configurable: !0 } }), Object.defineProperty(t, "prototype", { writable: !1 }), e && _setPrototypeOf(t, e); }
function _setPrototypeOf(t, e) { return _setPrototypeOf = Object.setPrototypeOf ? Object.setPrototypeOf.bind() : function (t, e) { return t.__proto__ = e, t; }, _setPrototypeOf(t, e); }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }

var RoleAddMember = /*#__PURE__*/function (_Controller) {
  function RoleAddMember() {
    _classCallCheck(this, RoleAddMember);
    return _callSuper(this, RoleAddMember, arguments);
  }
  _inherits(RoleAddMember, _Controller);
  return _createClass(RoleAddMember, [{
    key: "checkSubmitEnable",
    value: function checkSubmitEnable() {
      var scaMember = this.scaMemberTarget.value;
      var memberId = Number(scaMember.replace(/_/g, ""));
      if (memberId > 0) {
        this.submitBtnTarget.disabled = false;
        this.submitBtnTarget.focus();
      } else {
        this.submitBtnTarget.disabled = true;
      }
    }
  }]);
}(_hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller);
_defineProperty(RoleAddMember, "targets", ["scaMember", "form", "submitBtn"]);
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["role-add-member"] = RoleAddMember;

/***/ }),

/***/ "./assets/js/controllers/role-add-permission-controller.js":
/*!*****************************************************************!*\
  !*** ./assets/js/controllers/role-add-permission-controller.js ***!
  \*****************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _classCallCheck(a, n) { if (!(a instanceof n)) throw new TypeError("Cannot call a class as a function"); }
function _defineProperties(e, r) { for (var t = 0; t < r.length; t++) { var o = r[t]; o.enumerable = o.enumerable || !1, o.configurable = !0, "value" in o && (o.writable = !0), Object.defineProperty(e, _toPropertyKey(o.key), o); } }
function _createClass(e, r, t) { return r && _defineProperties(e.prototype, r), t && _defineProperties(e, t), Object.defineProperty(e, "prototype", { writable: !1 }), e; }
function _callSuper(t, o, e) { return o = _getPrototypeOf(o), _possibleConstructorReturn(t, _isNativeReflectConstruct() ? Reflect.construct(o, e || [], _getPrototypeOf(t).constructor) : o.apply(t, e)); }
function _possibleConstructorReturn(t, e) { if (e && ("object" == _typeof(e) || "function" == typeof e)) return e; if (void 0 !== e) throw new TypeError("Derived constructors may only return object or undefined"); return _assertThisInitialized(t); }
function _assertThisInitialized(e) { if (void 0 === e) throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); return e; }
function _isNativeReflectConstruct() { try { var t = !Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], function () {})); } catch (t) {} return (_isNativeReflectConstruct = function _isNativeReflectConstruct() { return !!t; })(); }
function _getPrototypeOf(t) { return _getPrototypeOf = Object.setPrototypeOf ? Object.getPrototypeOf.bind() : function (t) { return t.__proto__ || Object.getPrototypeOf(t); }, _getPrototypeOf(t); }
function _inherits(t, e) { if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function"); t.prototype = Object.create(e && e.prototype, { constructor: { value: t, writable: !0, configurable: !0 } }), Object.defineProperty(t, "prototype", { writable: !1 }), e && _setPrototypeOf(t, e); }
function _setPrototypeOf(t, e) { return _setPrototypeOf = Object.setPrototypeOf ? Object.setPrototypeOf.bind() : function (t, e) { return t.__proto__ = e, t; }, _setPrototypeOf(t, e); }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }

var RoleAddPermission = /*#__PURE__*/function (_Controller) {
  function RoleAddPermission() {
    _classCallCheck(this, RoleAddPermission);
    return _callSuper(this, RoleAddPermission, arguments);
  }
  _inherits(RoleAddPermission, _Controller);
  return _createClass(RoleAddPermission, [{
    key: "checkSubmitEnable",
    value: function checkSubmitEnable() {
      var permission = this.permissionTarget.value;
      var permissionId = Number(permission.replace(/_/g, ""));
      if (permissionId > 0) {
        this.submitBtnTarget.disabled = false;
        this.submitBtnTarget.focus();
      } else {
        this.submitBtnTarget.disabled = true;
      }
    }
  }]);
}(_hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller);
_defineProperty(RoleAddPermission, "targets", ["permission", "form", "submitBtn"]);
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["role-add-permission"] = RoleAddPermission;

/***/ }),

/***/ "./plugins/Awards/Assets/js/controllers/award-form-controller.js":
/*!***********************************************************************!*\
  !*** ./plugins/Awards/Assets/js/controllers/award-form-controller.js ***!
  \***********************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _classCallCheck(a, n) { if (!(a instanceof n)) throw new TypeError("Cannot call a class as a function"); }
function _defineProperties(e, r) { for (var t = 0; t < r.length; t++) { var o = r[t]; o.enumerable = o.enumerable || !1, o.configurable = !0, "value" in o && (o.writable = !0), Object.defineProperty(e, _toPropertyKey(o.key), o); } }
function _createClass(e, r, t) { return r && _defineProperties(e.prototype, r), t && _defineProperties(e, t), Object.defineProperty(e, "prototype", { writable: !1 }), e; }
function _callSuper(t, o, e) { return o = _getPrototypeOf(o), _possibleConstructorReturn(t, _isNativeReflectConstruct() ? Reflect.construct(o, e || [], _getPrototypeOf(t).constructor) : o.apply(t, e)); }
function _possibleConstructorReturn(t, e) { if (e && ("object" == _typeof(e) || "function" == typeof e)) return e; if (void 0 !== e) throw new TypeError("Derived constructors may only return object or undefined"); return _assertThisInitialized(t); }
function _assertThisInitialized(e) { if (void 0 === e) throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); return e; }
function _isNativeReflectConstruct() { try { var t = !Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], function () {})); } catch (t) {} return (_isNativeReflectConstruct = function _isNativeReflectConstruct() { return !!t; })(); }
function _getPrototypeOf(t) { return _getPrototypeOf = Object.setPrototypeOf ? Object.getPrototypeOf.bind() : function (t) { return t.__proto__ || Object.getPrototypeOf(t); }, _getPrototypeOf(t); }
function _inherits(t, e) { if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function"); t.prototype = Object.create(e && e.prototype, { constructor: { value: t, writable: !0, configurable: !0 } }), Object.defineProperty(t, "prototype", { writable: !1 }), e && _setPrototypeOf(t, e); }
function _setPrototypeOf(t, e) { return _setPrototypeOf = Object.setPrototypeOf ? Object.setPrototypeOf.bind() : function (t, e) { return t.__proto__ = e, t; }, _setPrototypeOf(t, e); }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }

var awardForm = /*#__PURE__*/function (_Controller) {
  function awardForm() {
    _classCallCheck(this, awardForm);
    return _callSuper(this, awardForm, arguments);
  }
  _inherits(awardForm, _Controller);
  return _createClass(awardForm, [{
    key: "initialize",
    value: function initialize() {
      this.items = [];
    }
  }, {
    key: "add",
    value: function add(event) {
      event.preventDefault();
      if (!this.newTarget.value) {
        return;
      }
      if (this.items.includes(this.newTarget.value)) {
        return;
      }
      var item = this.newTarget.value;
      this.items.push(item);
      this.createListItem(KMP_utils.sanitizeString(item));
      this.formValueTarget.value = JSON.stringify(this.items);
      this.newTarget.value = '';
    }
  }, {
    key: "remove",
    value: function remove(event) {
      event.preventDefault();
      var id = event.target.getAttribute('data-id');
      this.items = this.items.filter(function (item) {
        return item !== id;
      });
      this.formValueTarget.value = JSON.stringify(this.items);
      event.target.parentElement.remove();
    }
  }, {
    key: "connect",
    value: function connect() {
      var _this = this;
      if (this.formValueTarget.value) {
        this.items = JSON.parse(this.formValueTarget.value);
        this.items.forEach(function (item) {
          //create a remove button
          _this.createListItem(item);
        });
      }
    }
  }, {
    key: "createListItem",
    value: function createListItem(item) {
      var removeButton = document.createElement('button');
      removeButton.innerHTML = 'Remove';
      removeButton.setAttribute('data-action', 'click->awards-award-form#remove');
      removeButton.setAttribute('data-id', item);
      removeButton.setAttribute('class', 'btn btn-danger btn-sm');
      removeButton.setAttribute('type', 'button');
      //create a list item
      var listItem = document.createElement('li');
      var span = document.createElement('span');
      span.innerHTML = item;
      span.setAttribute('class', 'ms-2 me-auto');
      listItem.innerHTML = item;
      listItem.setAttribute('class', 'list-group-item d-flex justify-content-between align-items-start');
      listItem.appendChild(removeButton);
      this.displayListTarget.appendChild(listItem);
    }
  }]);
}(_hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller); // add to window.Controllers with a name of the controller
_defineProperty(awardForm, "targets", ["new", "formValue", "displayList"]);
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["awards-award-form"] = awardForm;

/***/ }),

/***/ "./plugins/Awards/Assets/js/controllers/recommendation-form-controller.js":
/*!********************************************************************************!*\
  !*** ./plugins/Awards/Assets/js/controllers/recommendation-form-controller.js ***!
  \********************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _classCallCheck(a, n) { if (!(a instanceof n)) throw new TypeError("Cannot call a class as a function"); }
function _defineProperties(e, r) { for (var t = 0; t < r.length; t++) { var o = r[t]; o.enumerable = o.enumerable || !1, o.configurable = !0, "value" in o && (o.writable = !0), Object.defineProperty(e, _toPropertyKey(o.key), o); } }
function _createClass(e, r, t) { return r && _defineProperties(e.prototype, r), t && _defineProperties(e, t), Object.defineProperty(e, "prototype", { writable: !1 }), e; }
function _callSuper(t, o, e) { return o = _getPrototypeOf(o), _possibleConstructorReturn(t, _isNativeReflectConstruct() ? Reflect.construct(o, e || [], _getPrototypeOf(t).constructor) : o.apply(t, e)); }
function _possibleConstructorReturn(t, e) { if (e && ("object" == _typeof(e) || "function" == typeof e)) return e; if (void 0 !== e) throw new TypeError("Derived constructors may only return object or undefined"); return _assertThisInitialized(t); }
function _assertThisInitialized(e) { if (void 0 === e) throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); return e; }
function _isNativeReflectConstruct() { try { var t = !Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], function () {})); } catch (t) {} return (_isNativeReflectConstruct = function _isNativeReflectConstruct() { return !!t; })(); }
function _getPrototypeOf(t) { return _getPrototypeOf = Object.setPrototypeOf ? Object.getPrototypeOf.bind() : function (t) { return t.__proto__ || Object.getPrototypeOf(t); }, _getPrototypeOf(t); }
function _inherits(t, e) { if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function"); t.prototype = Object.create(e && e.prototype, { constructor: { value: t, writable: !0, configurable: !0 } }), Object.defineProperty(t, "prototype", { writable: !1 }), e && _setPrototypeOf(t, e); }
function _setPrototypeOf(t, e) { return _setPrototypeOf = Object.setPrototypeOf ? Object.setPrototypeOf.bind() : function (t, e) { return t.__proto__ = e, t; }, _setPrototypeOf(t, e); }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }

var RecommendationForm = /*#__PURE__*/function (_Controller) {
  function RecommendationForm() {
    _classCallCheck(this, RecommendationForm);
    return _callSuper(this, RecommendationForm, arguments);
  }
  _inherits(RecommendationForm, _Controller);
  return _createClass(RecommendationForm, [{
    key: "submit",
    value: function submit(event) {
      this.callIntoCourtTarget.disabled = false;
      this.courtAvailabilityTarget.disabled = false;
      this.notFoundTarget.disabled = false;
    }
  }, {
    key: "setAward",
    value: function setAward(event) {
      var awardId = event.target.dataset.awardId;
      this.awardTarget.value = awardId;
      this.populateSpecialties(event);
    }
  }, {
    key: "populateAwardDescriptions",
    value: function populateAwardDescriptions(event) {
      var _this = this;
      var url = this.awardListUrlValue + "/" + event.target.value;
      fetch(url).then(function (response) {
        return response.json();
      }).then(function (data) {
        _this.awardDescriptionsTarget.innerHTML = "";
        var tabButtons = document.createElement("ul");
        tabButtons.classList.add("nav", "nav-pills");
        tabButtons.setAttribute("role", "tablist");
        var tabContentArea = document.createElement("div");
        tabContentArea.classList.add("tab-content");
        tabContentArea.classList.add("border");
        tabContentArea.classList.add("border-light-subtle");
        tabContentArea.classList.add("p-2");
        var awardComboData = _this.awardTarget.querySelector("[data-ac-target='dataList']");
        var specialtyComboData = _this.specialtyTarget.querySelector("[data-ac-target='dataList']");
        var specialtyTarget = _this.specialtyTarget;
        awardComboData.innerHTML = "";
        tabContentArea.innerHTML = "";
        _this.awardTarget.value = "";
        var active = "active";
        var show = "show";
        var selected = "true";
        if (data.length > 0) {
          data.forEach(function (award) {
            //create list item
            var li = document.createElement("li");
            li.classList.add("list-group-item");
            li.innerHTML = award.name;
            li.setAttribute("data-ac-value", award.id);
            if (award.specialties != null) {
              li.setAttribute("data-specialties", award.specialties);
            }
            awardComboData.appendChild(li);
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
          _this.awardDescriptionsTarget.appendChild(tabButtons);
          _this.awardDescriptionsTarget.appendChild(tabContentArea);
          _this.awardTarget.disabled = false;
        } else {
          var li = document.createElement("li");
          li.classList.add("list-group-item");
          li.innerHTML = "No awards available";
          li.setAttribute("data-ac-value", "No awards available");
          awardComboData.appendChild(li);
          _this.awardTarget.value = "No awards available";
          _this.awardTarget.disabled = true;
        }
      });
    }
  }, {
    key: "populateSpecialties",
    value: function populateSpecialties(event) {
      var awardId = this.awardTarget.value;
      var specialtyData = this.specialtyTarget.querySelector("[data-ac-target='dataList']");
      specialtyData.innerHTML = "";
      var specialties = this.awardTarget.querySelector("[data-ac-value='" + awardId + "']").getAttribute("data-specialties");
      if (specialties != "" && specialties != null) {
        var specialtyArray = JSON.parse(specialties);
        specialtyArray.forEach(function (specialty) {
          var li = document.createElement("li");
          li.classList.add("list-group-item");
          li.innerHTML = specialty;
          li.setAttribute("data-ac-value", specialty);
          specialtyData.appendChild(li);
        });
        this.specialtyTarget.value = "";
        this.specialtyTarget.disabled = false;
        this.specialtyTarget.hidden = false;
      } else {
        var li = document.createElement("li");
        li.classList.add("list-group-item");
        li.innerHTML = "No specialties available";
        li.setAttribute("data-ac-value", "No specialties available");
        specialtyData.appendChild(li);
        this.specialtyTarget.value = "No specialties available";
        this.specialtyTarget.disabled = true;
      }
    }
  }, {
    key: "loadScaMemberInfo",
    value: function loadScaMemberInfo(event) {
      //reset member metadata area
      this.externalLinksTarget.innerHTML = "";
      this.courtAvailabilityTarget.value = "";
      this.callIntoCourtTarget.value = "";
      this.callIntoCourtTarget.disabled = false;
      this.courtAvailabilityTarget.disabled = false;
      var memberId = Number(event.target.value.replace(/_/g, ""));
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
  }, {
    key: "loadMember",
    value: function loadMember(memberId) {
      var _this2 = this;
      var url = this.publicProfileUrlValue + "/" + memberId;
      fetch(url).then(function (response) {
        return response.json();
      }).then(function (data) {
        _this2.callIntoCourtTarget.value = data.additional_info.CallIntoCourt;
        _this2.courtAvailabilityTarget.value = data.additional_info.CourtAvailability;
        if (_this2.callIntoCourtTarget.value != "") {
          _this2.callIntoCourtTarget.disabled = true;
        } else {
          _this2.callIntoCourtTarget.disabled = false;
        }
        if (_this2.courtAvailabilityTarget.value != "") {
          _this2.courtAvailabilityTarget.disabled = true;
        } else {
          _this2.courtAvailabilityTarget.disabled = false;
        }
        _this2.externalLinksTarget.innerHTML = "";
        var keys = Object.keys(data.external_links);
        if (keys.length > 0) {
          var LinksTitle = document.createElement("div");
          LinksTitle.innerHTML = "<h5>Public Links</h5>";
          LinksTitle.classList.add("col-12");
          _this2.externalLinksTarget.appendChild(LinksTitle);
          for (var key in data.external_links) {
            var div = document.createElement("div");
            div.classList.add("col-12");
            var a = document.createElement("a");
            a.href = data.external_links[key];
            a.text = key;
            a.target = "_blank";
            div.appendChild(a);
            _this2.externalLinksTarget.appendChild(div);
          }
        } else {
          var noLink = document.createElement("div");
          noLink.innerHTML = "<h5>No links available</h5>";
          noLink.classList.add("col-12");
          _this2.externalLinksTarget.appendChild(noLink);
        }
      });
    }
  }, {
    key: "acConnected",
    value: function acConnected(event) {
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
  }, {
    key: "connect",
    value: function connect() {
      this.notFoundTarget.checked = false;
      this.notFoundTarget.disabled = true;
      this.reasonTarget.value = "";
      this.eventsTargets.forEach(function (element) {
        element.checked = false;
      });
    }
  }]);
}(_hotwired_stimulus__WEBPACK_IMPORTED_MODULE_0__.Controller); // add to window.Controllers with a name of the controller
_defineProperty(RecommendationForm, "targets", ["scaMember", "notFound", "branch", "callIntoCourt", "courtAvailability", "externalLinks", "awardDescriptions", "award", "reason", "events", "specialty"]);
_defineProperty(RecommendationForm, "values", {
  publicProfileUrl: String,
  awardListUrl: String
});
if (!window.Controllers) {
  window.Controllers = {};
}
window.Controllers["awards-rec-form"] = RecommendationForm;

/***/ }),

/***/ "./assets/css/app.css":
/*!****************************!*\
  !*** ./assets/css/app.css ***!
  \****************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./assets/css/signin.css":
/*!*******************************!*\
  !*** ./assets/css/signin.css ***!
  \*******************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./assets/css/cover.css":
/*!******************************!*\
  !*** ./assets/css/cover.css ***!
  \******************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./assets/css/dashboard.css":
/*!**********************************!*\
  !*** ./assets/css/dashboard.css ***!
  \**********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ })

},
/******/ __webpack_require__ => { // webpackRuntimeModules
/******/ var __webpack_exec__ = (moduleId) => (__webpack_require__(__webpack_require__.s = moduleId))
/******/ __webpack_require__.O(0, ["js/core","css/app","css/cover","css/signin","css/dashboard"], () => (__webpack_exec__("./assets/js/controllers/app-setting-form-controller.js"), __webpack_exec__("./assets/js/controllers/auto-complete-controller.js"), __webpack_exec__("./assets/js/controllers/detail-tabs-controller.js"), __webpack_exec__("./assets/js/controllers/image-preview-controller.js"), __webpack_exec__("./assets/js/controllers/member-mobile-card-profile-controller.js"), __webpack_exec__("./assets/js/controllers/member-mobile-card-pwa-controller.js"), __webpack_exec__("./assets/js/controllers/member-unique-email-controller.js"), __webpack_exec__("./assets/js/controllers/member-verify-form-controller.js"), __webpack_exec__("./assets/js/controllers/modal-opener-controller.js"), __webpack_exec__("./assets/js/controllers/nav-bar-controller.js"), __webpack_exec__("./assets/js/controllers/permission-add-role-controller.js"), __webpack_exec__("./assets/js/controllers/role-add-member-controller.js"), __webpack_exec__("./assets/js/controllers/role-add-permission-controller.js"), __webpack_exec__("./plugins/Awards/Assets/js/controllers/award-form-controller.js"), __webpack_exec__("./plugins/Awards/Assets/js/controllers/recommendation-form-controller.js"), __webpack_exec__("./assets/css/app.css"), __webpack_exec__("./assets/css/signin.css"), __webpack_exec__("./assets/css/cover.css"), __webpack_exec__("./assets/css/dashboard.css")));
/******/ var __webpack_exports__ = __webpack_require__.O();
/******/ }
]);