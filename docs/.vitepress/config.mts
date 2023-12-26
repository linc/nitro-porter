import { defineConfig } from 'vitepress';

// https://vitepress.dev/reference/site-config
export default defineConfig({
    title: 'Nitro Porter',
    description: 'Data migrations for communities',

    themeConfig: {
        // https://vitepress.dev/reference/default-theme-config
        nav: [
            { text: 'Home', link: '/' },
        ],

        sidebar: [
            {
                text: 'Introduction',
                items: [
                    { text: 'Overview', link: '/' },
                    { text: 'Migration Planning', link: '/migrations' },
                ],
            },
            {
                text: 'Migrations',
                items: [
                    { text: 'Quick Start', link: '/usage' },
                    { text: 'Supported Sources', link: '/sources' },
                    { text: 'Supported Targets', link: '/targets' },
                ],
            },
            {
                text: 'Development',
                items: [
                    { text: 'Developer Guide', link: '/develop' },
                ],
            },
        ],

        socialLinks: [
            { icon: 'github', link: 'https://github.com/linc/nitro-porter' },
        ],
    }
});
