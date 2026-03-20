// These CSS class names match the original ReloginDialog's CSS module from the Neos UI host.
// The styles are already loaded by the host — we just need the class name references.
// If the host uses hashed CSS module names, these won't match and the dialog will be unstyled
// but still functional. In that case, we fall back to inline styles.
const style: Record<string, string> = {
    modalContents: 'modalContents',
    inputFieldWrapper: 'inputFieldWrapper',
    inputField: 'inputField',
    loginButton: 'loginButton',
};

export default style;
