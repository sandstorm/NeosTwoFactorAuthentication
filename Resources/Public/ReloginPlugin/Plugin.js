"use strict";
(() => {
  var __create = Object.create;
  var __defProp = Object.defineProperty;
  var __getOwnPropDesc = Object.getOwnPropertyDescriptor;
  var __getOwnPropNames = Object.getOwnPropertyNames;
  var __getProtoOf = Object.getPrototypeOf;
  var __hasOwnProp = Object.prototype.hasOwnProperty;
  var __esm = (fn, res) => function __init() {
    return fn && (res = (0, fn[__getOwnPropNames(fn)[0]])(fn = 0)), res;
  };
  var __commonJS = (cb, mod) => function __require() {
    return mod || (0, cb[__getOwnPropNames(cb)[0]])((mod = { exports: {} }).exports, mod), mod.exports;
  };
  var __copyProps = (to, from, except, desc) => {
    if (from && typeof from === "object" || typeof from === "function") {
      for (let key of __getOwnPropNames(from))
        if (!__hasOwnProp.call(to, key) && key !== except)
          __defProp(to, key, { get: () => from[key], enumerable: !(desc = __getOwnPropDesc(from, key)) || desc.enumerable });
    }
    return to;
  };
  var __toESM = (mod, isNodeMode, target) => (target = mod != null ? __create(__getProtoOf(mod)) : {}, __copyProps(
    // If the importer is in node compatibility mode or this is not an ESM
    // file that has been converted to a CommonJS file using a Babel-
    // compatible transform (i.e. "__esModule" has not been set), then set
    // "default" to the CommonJS "module.exports" for node compatibility.
    isNodeMode || !mod || !mod.__esModule ? __defProp(target, "default", { value: mod, enumerable: true }) : target,
    mod
  ));

  // node_modules/@neos-project/neos-ui-extensibility/dist/readFromConsumerApi.js
  function readFromConsumerApi(key) {
    return (...args) => {
      if (window["@Neos:HostPluginAPI"] && window["@Neos:HostPluginAPI"][`@${key}`]) {
        return window["@Neos:HostPluginAPI"][`@${key}`](...args);
      }
      throw new Error("You are trying to read from a consumer api that hasn't been initialized yet!");
    };
  }
  var init_readFromConsumerApi = __esm({
    "node_modules/@neos-project/neos-ui-extensibility/dist/readFromConsumerApi.js"() {
    }
  });

  // node_modules/@neos-project/neos-ui-extensibility/dist/shims/vendor/react/index.js
  var require_react = __commonJS({
    "node_modules/@neos-project/neos-ui-extensibility/dist/shims/vendor/react/index.js"(exports, module) {
      init_readFromConsumerApi();
      module.exports = readFromConsumerApi("vendor")().React;
    }
  });

  // node_modules/@neos-project/neos-ui-extensibility/dist/shims/vendor/react-redux/index.js
  var require_react_redux = __commonJS({
    "node_modules/@neos-project/neos-ui-extensibility/dist/shims/vendor/react-redux/index.js"(exports, module) {
      init_readFromConsumerApi();
      module.exports = readFromConsumerApi("vendor")().reactRedux;
    }
  });

  // node_modules/@neos-project/neos-ui-extensibility/dist/shims/vendor/plow-js/index.js
  var require_plow_js = __commonJS({
    "node_modules/@neos-project/neos-ui-extensibility/dist/shims/vendor/plow-js/index.js"(exports, module) {
      init_readFromConsumerApi();
      module.exports = readFromConsumerApi("vendor")().plow;
    }
  });

  // node_modules/@neos-project/neos-ui-extensibility/dist/shims/neosProjectPackages/neos-ui-decorators/index.js
  var require_neos_ui_decorators = __commonJS({
    "node_modules/@neos-project/neos-ui-extensibility/dist/shims/neosProjectPackages/neos-ui-decorators/index.js"(exports, module) {
      init_readFromConsumerApi();
      module.exports = readFromConsumerApi("NeosProjectPackages")().NeosUiDecorators;
    }
  });

  // node_modules/@neos-project/neos-ui-extensibility/dist/shims/neosProjectPackages/neos-ui-redux-store/index.js
  var require_neos_ui_redux_store = __commonJS({
    "node_modules/@neos-project/neos-ui-extensibility/dist/shims/neosProjectPackages/neos-ui-redux-store/index.js"(exports, module) {
      init_readFromConsumerApi();
      module.exports = readFromConsumerApi("NeosProjectPackages")().NeosUiReduxStore;
    }
  });

  // node_modules/@neos-project/neos-ui-extensibility/dist/shims/neosProjectPackages/react-ui-components/index.js
  var require_react_ui_components = __commonJS({
    "node_modules/@neos-project/neos-ui-extensibility/dist/shims/neosProjectPackages/react-ui-components/index.js"(exports, module) {
      init_readFromConsumerApi();
      module.exports = readFromConsumerApi("NeosProjectPackages")().ReactUiComponents;
    }
  });

  // node_modules/@neos-project/neos-ui-extensibility/dist/shims/neosProjectPackages/neos-ui-i18n/index.js
  var require_neos_ui_i18n = __commonJS({
    "node_modules/@neos-project/neos-ui-extensibility/dist/shims/neosProjectPackages/neos-ui-i18n/index.js"(exports, module) {
      init_readFromConsumerApi();
      module.exports = readFromConsumerApi("NeosProjectPackages")().NeosUiI18n;
    }
  });

  // node_modules/@neos-project/neos-ui-extensibility/dist/index.js
  init_readFromConsumerApi();
  var dist_default = readFromConsumerApi("manifest");

  // src/ReloginDialogWithOtp.tsx
  var import_react = __toESM(require_react());
  var import_react_redux = __toESM(require_react_redux());
  var import_plow_js = __toESM(require_plow_js());
  var import_neos_ui_decorators = __toESM(require_neos_ui_decorators());
  var import_neos_ui_redux_store = __toESM(require_neos_ui_redux_store());

  // node_modules/@neos-project/neos-ui-extensibility/dist/shims/neosProjectPackages/neos-ui-backend-connector/index.js
  init_readFromConsumerApi();
  var neos_ui_backend_connector_default = readFromConsumerApi("NeosProjectPackages")().NeosUiBackendConnectorDefault;
  var { fetchWithErrorHandling } = readFromConsumerApi("NeosProjectPackages")().NeosUiBackendConnector;

  // src/ReloginDialogWithOtp.tsx
  var import_react_ui_components = __toESM(require_react_ui_components());
  var import_neos_ui_i18n = __toESM(require_neos_ui_i18n());

  // src/api.ts
  async function fetchSecondFactorStatus(csrfToken) {
    console.log("[2FA API] fetchSecondFactorStatus, CSRF token present:", !!csrfToken);
    const response = await fetch("/neos/api/second-factor-status", {
      credentials: "same-origin",
      headers: {
        "Accept": "application/json",
        "X-Flow-Csrftoken": csrfToken
      }
    });
    console.log("[2FA API] fetchSecondFactorStatus response:", response.status, response.statusText);
    if (!response.ok) {
      const text = await response.text();
      console.error("[2FA API] fetchSecondFactorStatus error body:", text);
      throw new Error(`Status check failed: ${response.status} - ${text}`);
    }
    const json = await response.json();
    console.log("[2FA API] fetchSecondFactorStatus result:", json);
    return json;
  }
  async function verifySecondFactor(otp, csrfToken) {
    console.log("[2FA API] verifySecondFactor, OTP length:", otp.length, ", CSRF token present:", !!csrfToken);
    const response = await fetch("/neos/api/second-factor-verify", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
        "X-Flow-Csrftoken": csrfToken
      },
      body: JSON.stringify({ otp })
    });
    console.log("[2FA API] verifySecondFactor response:", response.status, response.statusText);
    const json = await response.json();
    console.log("[2FA API] verifySecondFactor result:", json);
    return json;
  }

  // src/ReloginDialogStyles.ts
  var style = {
    modalContents: "modalContents",
    inputFieldWrapper: "inputFieldWrapper",
    inputField: "inputField",
    loginButton: "loginButton"
  };
  var ReloginDialogStyles_default = style;

  // src/ReloginDialogWithOtp.tsx
  var ReloginDialogWithOtp = (props) => {
    const { authenticationTimeout, i18nRegistry, reauthenticationSucceeded } = props;
    const [phase, setPhase] = (0, import_react.useState)("password");
    const [username, setUsername] = (0, import_react.useState)("");
    const [password, setPassword] = (0, import_react.useState)("");
    const [isLoading, setIsLoading] = (0, import_react.useState)(false);
    const [message, setMessage] = (0, import_react.useState)(false);
    const [otp, setOtp] = (0, import_react.useState)("");
    const [otpError, setOtpError] = (0, import_react.useState)(null);
    const [isOtpSubmitting, setIsOtpSubmitting] = (0, import_react.useState)(false);
    const otpInputRef = (0, import_react.useRef)(null);
    const csrfTokenRef = (0, import_react.useRef)(null);
    console.log("[2FA Plugin] ReloginDialogWithOtp RENDER, phase:", phase, "authenticationTimeout:", authenticationTimeout);
    (0, import_react.useEffect)(() => {
      if (phase === "otp" && otpInputRef.current) {
        otpInputRef.current.focus();
      }
    }, [phase]);
    (0, import_react.useEffect)(() => {
      if (!authenticationTimeout) {
        setPhase("password");
        setUsername("");
        setPassword("");
        setIsLoading(false);
        setMessage(false);
        setOtp("");
        setOtpError(null);
        setIsOtpSubmitting(false);
        csrfTokenRef.current = null;
      }
    }, [authenticationTimeout]);
    const completeRelogin = (0, import_react.useCallback)((csrfToken) => {
      console.log("[2FA Plugin] completeRelogin \u2014 replaying queued requests");
      fetchWithErrorHandling.updateCsrfTokenAndWorkThroughQueue(csrfToken);
      reauthenticationSucceeded();
    }, [reauthenticationSucceeded]);
    const handleTryLogin = (0, import_react.useCallback)(
      async () => {
        console.log("[2FA Plugin] handleTryLogin called");
        setIsLoading(true);
        setMessage(false);
        const newCsrfToken = await neos_ui_backend_connector_default.get().endpoints.tryLogin(username, password);
        console.log("[2FA Plugin] tryLogin result, got CSRF token:", !!newCsrfToken);
        if (newCsrfToken) {
          csrfTokenRef.current = newCsrfToken;
          setPhase("checking");
          try {
            console.log("[2FA Plugin] Checking second factor status...");
            const result = await fetchSecondFactorStatus(newCsrfToken);
            console.log("[2FA Plugin] Second factor status:", result);
            if (result.secondFactorRequired) {
              console.log("[2FA Plugin] 2FA required, showing OTP form");
              setPhase("otp");
              setIsLoading(false);
            } else {
              console.log("[2FA Plugin] No 2FA required, completing relogin");
              completeRelogin(newCsrfToken);
            }
          } catch (e) {
            console.warn("[2FA Plugin] 2FA status check failed, completing relogin without 2FA:", e);
            completeRelogin(newCsrfToken);
          }
        } else {
          console.log("[2FA Plugin] Login failed \u2014 wrong credentials");
          setMessage(i18nRegistry.translate("Neos.Neos:Main:wrongCredentials", "The entered username or password was wrong"));
          setIsLoading(false);
        }
      },
      [username, password, i18nRegistry, completeRelogin]
    );
    const handleOtpSubmit = (0, import_react.useCallback)(async () => {
      if (!otp.trim() || otp.length < 6) return;
      setIsOtpSubmitting(true);
      setOtpError(null);
      try {
        console.log("[2FA Plugin] Verifying OTP...");
        const result = await verifySecondFactor(otp, csrfTokenRef.current);
        console.log("[2FA Plugin] OTP verify result:", result);
        if (result.success) {
          console.log("[2FA Plugin] OTP verified, completing relogin");
          if (csrfTokenRef.current) {
            completeRelogin(csrfTokenRef.current);
          }
        } else {
          console.log("[2FA Plugin] OTP invalid:", result.error);
          setOtpError(result.error || "Invalid OTP. Please try again.");
          setOtp("");
        }
      } catch (e) {
        console.error("[2FA Plugin] OTP verification error:", e);
        setOtpError("Verification failed. Please try again.");
        setOtp("");
      } finally {
        setIsOtpSubmitting(false);
      }
    }, [otp, completeRelogin]);
    const handleOtpChange = (0, import_react.useCallback)((value) => {
      const cleaned = value.replace(/\D/g, "").slice(0, 6);
      setOtp(cleaned);
    }, []);
    if (!authenticationTimeout) {
      return null;
    }
    if (phase === "password") {
      console.log("[2FA Plugin] Rendering password phase");
      return /* @__PURE__ */ import_react.default.createElement(
        import_react_ui_components.Dialog,
        {
          title: /* @__PURE__ */ import_react.default.createElement(import_neos_ui_i18n.default, { id: "Neos.Neos:Main:login.expired", fallback: "Your login has expired. Please log in again." }),
          style: "narrow",
          isOpen: true,
          id: "neos-ReloginDialog"
        },
        /* @__PURE__ */ import_react.default.createElement("div", { className: ReloginDialogStyles_default.modalContents }, /* @__PURE__ */ import_react.default.createElement(
          import_react_ui_components.TextInput,
          {
            className: ReloginDialogStyles_default.inputField,
            containerClassName: ReloginDialogStyles_default.inputFieldWrapper,
            value: username,
            name: "__authentication[Neos][Flow][Security][Authentication][Token][UsernamePassword][username]",
            placeholder: i18nRegistry.translate("Neos.Neos:Main:username", "Username"),
            onChange: setUsername,
            onEnterKey: handleTryLogin,
            setFocus: true
          }
        ), /* @__PURE__ */ import_react.default.createElement(
          import_react_ui_components.TextInput,
          {
            type: "password",
            className: ReloginDialogStyles_default.inputField,
            containerClassName: ReloginDialogStyles_default.inputFieldWrapper,
            value: password,
            name: "__authentication[Neos][Flow][Security][Authentication][Token][UsernamePassword][password]",
            placeholder: i18nRegistry.translate("Neos.Neos:Main:password", "Password"),
            onChange: setPassword,
            onEnterKey: handleTryLogin
          }
        ), /* @__PURE__ */ import_react.default.createElement(
          import_react_ui_components.Button,
          {
            key: "login",
            style: "brand",
            hoverStyle: "brand",
            onClick: handleTryLogin,
            disabled: isLoading,
            className: ReloginDialogStyles_default.loginButton
          },
          isLoading ? /* @__PURE__ */ import_react.default.createElement(import_neos_ui_i18n.default, { id: "Neos.Neos:Main:authenticating", fallback: "Authenticating" }) : /* @__PURE__ */ import_react.default.createElement(import_neos_ui_i18n.default, { id: "Neos.Neos:Main:login", fallback: "Login" })
        ), message ? /* @__PURE__ */ import_react.default.createElement(import_react_ui_components.Tooltip, { asError: true }, message) : null)
      );
    }
    if (phase === "checking") {
      return /* @__PURE__ */ import_react.default.createElement(
        import_react_ui_components.Dialog,
        {
          title: /* @__PURE__ */ import_react.default.createElement(import_neos_ui_i18n.default, { id: "Neos.Neos:Main:login.expired", fallback: "Your login has expired. Please log in again." }),
          style: "narrow",
          isOpen: true,
          id: "neos-ReloginDialog"
        },
        /* @__PURE__ */ import_react.default.createElement("div", { className: ReloginDialogStyles_default.modalContents }, /* @__PURE__ */ import_react.default.createElement("p", null, "Checking authentication..."))
      );
    }
    return /* @__PURE__ */ import_react.default.createElement(
      import_react_ui_components.Dialog,
      {
        title: "Two-Factor Authentication",
        style: "narrow",
        isOpen: true,
        id: "neos-ReloginDialog"
      },
      /* @__PURE__ */ import_react.default.createElement("div", { className: ReloginDialogStyles_default.modalContents }, /* @__PURE__ */ import_react.default.createElement("p", { style: styles.otpDescription }, "Please enter the verification code from your authenticator app."), /* @__PURE__ */ import_react.default.createElement(
        import_react_ui_components.TextInput,
        {
          containerClassName: ReloginDialogStyles_default.inputFieldWrapper,
          className: ReloginDialogStyles_default.inputField,
          value: otp,
          placeholder: "Enter 6-digit code",
          onChange: handleOtpChange,
          onEnterKey: handleOtpSubmit,
          setFocus: true
        }
      ), /* @__PURE__ */ import_react.default.createElement(
        import_react_ui_components.Button,
        {
          key: "verify",
          style: "brand",
          hoverStyle: "brand",
          onClick: handleOtpSubmit,
          disabled: isOtpSubmitting || otp.length < 6,
          className: ReloginDialogStyles_default.loginButton
        },
        isOtpSubmitting ? "Verifying..." : "Verify"
      ), otpError ? /* @__PURE__ */ import_react.default.createElement(import_react_ui_components.Tooltip, { asError: true }, otpError) : null)
    );
  };
  var styles = {
    otpDescription: {
      marginBottom: "16px",
      fontSize: "14px",
      lineHeight: "1.5"
    }
  };
  var NeosDecorated = (0, import_neos_ui_decorators.neos)((globalRegistry) => ({
    i18nRegistry: globalRegistry.get("i18n")
  }))(ReloginDialogWithOtp);
  var Connected = (0, import_react_redux.connect)(
    (0, import_plow_js.$transform)({
      authenticationTimeout: import_neos_ui_redux_store.selectors.System.authenticationTimeout
    }),
    {
      reauthenticationSucceeded: import_neos_ui_redux_store.actions.System.reauthenticationSucceeded
    }
  )(NeosDecorated);
  var ReloginDialogWithOtp_default = Connected;

  // src/manifest.ts
  console.log("[2FA Plugin] manifest.tsx loaded");
  dist_default("Sandstorm.NeosTwoFactorAuthentication:ReloginDialog", {}, (globalRegistry) => {
    console.log("[2FA Plugin] manifest callback executing");
    const containerRegistry = globalRegistry.get("containers");
    console.log("[2FA Plugin] containerRegistry:", containerRegistry);
    if (!containerRegistry) {
      console.error("[2FA Plugin] containerRegistry is null/undefined! Available registries:", globalRegistry._registry ? Object.keys(globalRegistry._registry) : "unknown");
      return;
    }
    const existingKey = containerRegistry.get("Modals/ReloginDialog");
    console.log("[2FA Plugin] Existing Modals/ReloginDialog:", existingKey);
    containerRegistry.set(
      "Modals/ReloginDialog",
      ReloginDialogWithOtp_default
    );
    console.log("[2FA Plugin] Successfully registered ReloginDialogWithOtp");
  });
})();
