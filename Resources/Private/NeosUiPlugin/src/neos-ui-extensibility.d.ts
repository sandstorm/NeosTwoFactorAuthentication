declare module '@neos-project/neos-ui-extensibility' {
    type ManifestCallback = (globalRegistry: any) => void;
    function manifest(identifier: string, options: Record<string, unknown>, callback: ManifestCallback): void;
    export default manifest;
}

declare module '@neos-project/neos-ui-decorators' {
    export function neos(mapRegistryToProps: (globalRegistry: any) => Record<string, any>): (component: any) => any;
}

declare module '@neos-project/neos-ui-redux-store' {
    export const actions: any;
    export const selectors: any;
}

declare module '@neos-project/neos-ui-backend-connector' {
    const backend: {
        get(): {
            endpoints: {
                tryLogin(username: string, password: string): Promise<string | false>;
            };
        };
    };
    export default backend;

    export const fetchWithErrorHandling: {
        updateCsrfTokenAndWorkThroughQueue(csrfToken: string): void;
    };
}

declare module '@neos-project/react-ui-components' {
    export const Button: any;
    export const Dialog: any;
    export const TextInput: any;
    export const Tooltip: any;
}

declare module '@neos-project/neos-ui-i18n' {
    const I18n: any;
    export default I18n;
}

declare module 'plow-js' {
    export function $transform(transformations: Record<string, any>): (state: any) => any;
}

declare module 'react-redux' {
    export function connect(mapStateToProps: any, mapDispatchToProps?: any): (component: any) => any;
}

declare module '*.module.css' {
    const styles: Record<string, string>;
    export default styles;
}
