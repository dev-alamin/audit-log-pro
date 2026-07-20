/** @type {import('tailwindcss').Config} */

module.exports = {
	content: [ './assets/js/src/**/*.js' ],
	corePlugins: {
		preflight: false, // critical — don't let Tailwind reset wp-admin's own base styles
	},
	important: '#adtlogpro-admin-root', // scopes every utility's specificity to inside our mount point
	theme: {
		extend: {
			colors: {
				'alp-ink': '#0f172a',
			},
		},
	},
	plugins: [],
};
