import type { Config } from "tailwindcss";

const config: Config = {
    content: [
        "./resources/js/**/*.{js,ts,jsx,tsx}",
        "./resources/views/**/*.blade.php",
        "./app/Filament/**/*.php",
        "./vendor/filament/**/*.blade.php",
    ],
    safelist: [
        'bg-cream',
        'bg-white',
        'bg-primary',
        'bg-primary-lighter',
        'bg-primary-lightest',
        'bg-secondary',
        'text-left',
        'text-center',
        'text-right',
        'prose-p:text-left',
        'prose-p:text-center',
        'prose-p:text-right',
        'prose-headings:text-left',
        'prose-headings:text-center',
        'prose-headings:text-right',
        // Recharts colors
        'text-[#8884d8]',
        'text-[#82ca9d]',
        'text-[#ffc658]',
        'text-[#ff7300]',
        'text-[#413ea0]',
        'text-[#00c49f]',
    ],
    theme: {
        extend: {
            fontFamily: {
                title: ['Knewave', 'cursive'],
            },
            backgroundImage: {
                "gradient-radial": "radial-gradient(var(--tw-gradient-stops))",
                "gradient-conic": "conic-gradient(from 180deg at 50% 50%, var(--tw-gradient-stops))",
            },
            colors: {
                // Custom warm-white for page backgrounds (renamed from 'white' to avoid
                // conflicting with Tailwind's built-in white color used by Filament utilities)
                'cream': {
                    DEFAULT: 'hsl(20 13% 95% / <alpha-value>)',
                    darker: 'hsl(0 0% 85% / <alpha-value>)',
                },
                'primary': {
                    lightest: 'hsl(169 28% 89% / <alpha-value>)',
                    lighter: 'hsl(185 36% 70% / <alpha-value>)',
                    DEFAULT: 'hsl(192 89% 25% / <alpha-value>)',
                    darker: 'hsl(192 89% 15% / <alpha-value>)',
                },
                'secondary': {
                    DEFAULT: 'hsl(0 1% 15% / <alpha-value>)',
                },
                'orange': 'hsl(11 61% 58% / <alpha-value>)',
            },
            zIndex: {
                '-1': '-1',
            },
        },
    },
    plugins: [
        require('@tailwindcss/typography'),
        ({ addComponents, theme }: { addComponents: any; theme: any }) => {
            addComponents(
                {
                    '.container': {
                        margin: '0 auto',
                        padding: '0 1.5rem',
                        width: '100%',
                        maxWidth: '1600px',

                        '@screen md': {
                            padding: '0 3rem',
                        },

                        '@screen lg': {
                            padding: '0 4rem',
                        },

                        '@screen xl': {
                            padding: '0 8rem',
                        },
                    },
                },
                ['responsive']
            );
        },
    ],
    corePlugins: {
        container: false,
    },
};

export default config;
