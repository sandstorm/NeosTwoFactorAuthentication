import React, { CSSProperties, useCallback, useEffect, useRef, useState } from "react";
import { connect } from "react-redux";
import { $transform } from "plow-js";
import { neos } from "@neos-project/neos-ui-decorators";
import { actions, selectors } from "@neos-project/neos-ui-redux-store";
import backend, { fetchWithErrorHandling } from "@neos-project/neos-ui-backend-connector";
import { Button, Dialog, TextInput, Tooltip } from "@neos-project/react-ui-components";
import I18n from "@neos-project/neos-ui-i18n";
import { fetchSecondFactorStatus, verifySecondFactor } from "./api";

import style from "./ReloginDialogStyles";

type Phase = "password" | "checking" | "otp";

interface I18nRegistry {
  translate(id: string, fallback: string): string;
}

interface ReloginDialogProps {
  i18nRegistry: I18nRegistry;
  authenticationTimeout: boolean;
  reauthenticationSucceeded: () => void;
}

/**
 * Replacement for the Neos ReloginDialog that adds a second-factor (OTP) step.
 *
 * Flow:
 * 1. Show password form (same as original ReloginDialog)
 * 2. On successful password login, hold the CSRF token but don't replay queued requests yet
 * 3. Check if 2FA is required for this account
 * 4. If yes, show OTP input and verify
 * 5. Only after both steps succeed, replay queued requests via updateCsrfTokenAndWorkThroughQueue
 */
const ReloginDialogWithOtp: React.FC<ReloginDialogProps> = (props) => {
    const { authenticationTimeout, i18nRegistry, reauthenticationSucceeded } = props;

    const [phase, setPhase] = useState<Phase>("password");
    const [username, setUsername] = useState("");
    const [password, setPassword] = useState("");
    const [isLoading, setIsLoading] = useState(false);
    const [message, setMessage] = useState<string | false>(false);

    // OTP state
    const [otp, setOtp] = useState("");
    const [otpError, setOtpError] = useState<string | null>(null);
    const [isOtpSubmitting, setIsOtpSubmitting] = useState(false);
    const otpInputRef = useRef<HTMLInputElement>(null);

    // Store the CSRF token between password success and OTP verification
    const csrfTokenRef = useRef<string | null>(null);

    console.log("[2FA Plugin] ReloginDialogWithOtp RENDER, phase:", phase, "authenticationTimeout:", authenticationTimeout);

    // Focus OTP input when entering that phase
    useEffect(() => {
      if (phase === "otp" && otpInputRef.current) {
        otpInputRef.current.focus();
      }
    }, [phase]);

    // Reset state when dialog is dismissed
    useEffect(() => {
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

    const completeRelogin = useCallback((csrfToken: string) => {
      console.log("[2FA Plugin] completeRelogin — replaying queued requests");
      fetchWithErrorHandling.updateCsrfTokenAndWorkThroughQueue(csrfToken);
      reauthenticationSucceeded();
    }, [reauthenticationSucceeded]);

    const handleTryLogin = useCallback(
      async () => {
        console.log("[2FA Plugin] handleTryLogin called");
        setIsLoading(true);
        setMessage(false);

        const newCsrfToken = await backend.get().endpoints.tryLogin(username, password);
        console.log("[2FA Plugin] tryLogin result, got CSRF token:", !!newCsrfToken);

        if (newCsrfToken) {
          // Password succeeded — now check if 2FA is needed
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
          console.log("[2FA Plugin] Login failed — wrong credentials");
          setMessage(i18nRegistry.translate("Neos.Neos:Main:wrongCredentials", "The entered username or password was wrong"));
          setIsLoading(false);
        }
      },
      [username, password, i18nRegistry, completeRelogin]
    );

    const handleOtpSubmit = useCallback(async () => {
      if (!otp.trim() || otp.length < 6) return;

      setIsOtpSubmitting(true);
      setOtpError(null);

      try {
        console.log("[2FA Plugin] Verifying OTP...");
        const result = await verifySecondFactor(otp, csrfTokenRef.current!);
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

    const handleOtpChange = useCallback((value: string) => {
      // TextInput passes the value directly, not an event
      const cleaned = value.replace(/\D/g, "").slice(0, 6);
      setOtp(cleaned);
    }, []);

    if (!authenticationTimeout) {
      return null;
    }

// Phase: Password
    if (phase === "password") {
      console.log("[2FA Plugin] Rendering password phase");
      return (
        <Dialog
          title={<I18n id="Neos.Neos:Main:login.expired" fallback="Your login has expired. Please log in again." />}
          style="narrow"
          isOpen
          id="neos-ReloginDialog"
        >
          <div className={style.modalContents}>
            <TextInput
              className={style.inputField}
              containerClassName={style.inputFieldWrapper}
              value={username}
              name="__authentication[Neos][Flow][Security][Authentication][Token][UsernamePassword][username]"
              placeholder={i18nRegistry.translate("Neos.Neos:Main:username", "Username")}
              onChange={setUsername}
              onEnterKey={handleTryLogin}
              setFocus={true}
            />
            <TextInput
              type="password"
              className={style.inputField}
              containerClassName={style.inputFieldWrapper}
              value={password}
              name="__authentication[Neos][Flow][Security][Authentication][Token][UsernamePassword][password]"
              placeholder={i18nRegistry.translate("Neos.Neos:Main:password", "Password")}
              onChange={setPassword}
              onEnterKey={handleTryLogin}
            />
            <Button
              key="login"
              style="brand"
              hoverStyle="brand"
              onClick={handleTryLogin}
              disabled={isLoading}
              className={style.loginButton}
            >
              {isLoading
                ? <I18n id="Neos.Neos:Main:authenticating" fallback="Authenticating" />
                : <I18n id="Neos.Neos:Main:login" fallback="Login" />
              }
            </Button>
            {message ? <Tooltip asError={true}>{message}</Tooltip> : null}
          </div>
        </Dialog>
      );
    }

// Phase: Checking 2FA status
    if (phase === "checking") {
      return (
        <Dialog
          title={<I18n id="Neos.Neos:Main:login.expired" fallback="Your login has expired. Please log in again." />}
          style="narrow"
          isOpen
          id="neos-ReloginDialog"
        >
          <div className={style.modalContents}>
            <p>Checking authentication...</p>
          </div>
        </Dialog>
      );
    }

// Phase: OTP input
    return (
      <Dialog
        title="Two-Factor Authentication"
        style="narrow"
        isOpen
        id="neos-ReloginDialog"
      >
        <div className={style.modalContents}>
          <p style={styles.otpDescription}>
            Please enter the verification code from your authenticator app.
          </p>

          <TextInput
            containerClassName={style.inputFieldWrapper}
            className={style.inputField}
            value={otp}
            placeholder="Enter 6-digit code"
            onChange={handleOtpChange}
            onEnterKey={handleOtpSubmit}
            setFocus={true}
          />

          <Button
            key="verify"
            style="brand"
            hoverStyle="brand"
            onClick={handleOtpSubmit}
            disabled={isOtpSubmitting || otp.length < 6}
            className={style.loginButton}
          >
            {isOtpSubmitting ? "Verifying..." : "Verify"}
          </Button>

          {otpError ? <Tooltip asError={true}>{otpError}</Tooltip> : null}
        </div>
      </Dialog>
    );
  }
;

const styles: Record<string, CSSProperties> = {
  otpDescription: {
    marginBottom: "16px",
    fontSize: "14px",
    lineHeight: "1.5"
  }
};

// Apply the same decorators as the original ReloginDialog
const NeosDecorated = neos((globalRegistry: any) => ({
  i18nRegistry: globalRegistry.get("i18n")
}))(ReloginDialogWithOtp);

const Connected = connect(
  $transform({
    authenticationTimeout: selectors.System.authenticationTimeout
  }),
  {
    reauthenticationSucceeded: actions.System.reauthenticationSucceeded
  }
)(NeosDecorated);

export default Connected;
