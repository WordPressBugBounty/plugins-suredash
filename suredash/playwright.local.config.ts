import { defineConfig, devices } from '@playwright/test';

/**
 * Local testing config — no global-setup (login handled per-test).
 * Usage: npx playwright test --config=playwright.local.config.ts
 */
export default defineConfig( {
	testDir: './tests/e2e/play/specs',
	timeout: 100 * 1000,
	expect: {
		timeout: 5000,
	},
	fullyParallel: true,
	workers: 1,
	reporter: 'list',
	use: {
		baseURL: process.env.baseURL || 'http://suredash.local/',
		headless: true,
		viewport: { width: 1280, height: 720 },
		ignoreHTTPSErrors: true,
		actionTimeout: 0,
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
	outputDir: 'tests/e2e/play/test-results/',
} );
