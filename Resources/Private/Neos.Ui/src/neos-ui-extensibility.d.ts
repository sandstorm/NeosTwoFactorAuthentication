declare module '@neos-project/neos-ui-extensibility' {
    type ManifestCallback = (globalRegistry: any) => void;
    function manifest(identifier: string, options: Record<string, unknown>, callback: ManifestCallback): void;
    export default manifest;
}

declare module '@neos-project/neos-ui-redux-store' {
    export const actionTypes: any;
}

declare module 'redux-saga/effects' {
    export function take(pattern: string): any;
}
