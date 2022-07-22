import {
    LanguageClient,
    ServerOptions,
    LanguageClientOptions,
} from "vscode-languageclient";

import * as vscode from "vscode";

let languageClient: LanguageClient;

export async function activate(context: vscode.ExtensionContext): Promise<void> {
    const cmd = context.asAbsolutePath('laravel-dev-tools') as any;
    languageClient = createClient(cmd);

    languageClient.start();
}

export function deactivate() {
	if (!languageClient) {
		return undefined;
	}
	return languageClient.stop();
}

function createClient(cmd: any): LanguageClient {
    let serverOptions: ServerOptions = {
        command: cmd,
        args: [
            "lsp"
        ]
    };

    let clientOptions: LanguageClientOptions = {
        documentSelector: [
            { language: 'blade', scheme: 'file' },
            { language: 'blade', scheme: 'untitled' },
            { language: 'php', scheme: 'file' },
            { language: 'php', scheme: 'untitled' }
        ],
        initializationOptions: {}
    };

    languageClient = new LanguageClient(
        "bladeLsp",
        "Laravel blade lsp",
        serverOptions,
        clientOptions
    );

    return languageClient;
}