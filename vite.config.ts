import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { resolve } from 'node:path';
import { defineConfig } from 'vite';
import replace from '@rollup/plugin-replace';
import os from 'node:os';

function stripUseClientDirective(): import('vite').Plugin {
  return {
    name: 'strip-use-client-directive',
    enforce: 'pre' as const,
    transform(code, id) {
      if (
        id.endsWith('.js') ||
        id.endsWith('.ts') ||
        id.endsWith('.tsx') ||
        id.endsWith('.mjs')
      ) {
        if (code.includes('"use client"') || code.includes("'use client'")) {
          return code.replace(/['"]use client['"];?\s*/g, '');
        }
      }
    },
  };
}

function GetCurrentLocalIpAddress()
{
    const networkInterfaces = os.networkInterfaces()
    for (const interfaceName in networkInterfaces)
    {
        const interfaces = networkInterfaces[interfaceName]
        for (const iface of interfaces)
        {
            if (!iface.internal && iface.family === 'IPv4') return iface.address
        }
    }
    return 'No local IP found'
}

export default defineConfig({
    base: './',
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/dark-mode.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        replace({
            preventAssignment: true,
            "import { URL } from 'url'": false,
        }),
        stripUseClientDirective(),
        tailwindcss()
    ],
    server: {
        host: GetCurrentLocalIpAddress(),
        port: 5173,
        headers: {
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Methods': 'GET,POST,PUT,DELETE,OPTIONS',
            'Access-Control-Allow-Headers': '*',
        },
        watch: {
            ignored: ['**/vendor/**', '**/node_modules/**']
        },
        strictPort: true,
        cors: {
            origin: '*',
            methods: ['GET','POST','PUT','DELETE','PATCH','OPTIONS'],
            allowedHeaders: ['Content-Type', 'Authorization','X-Requested-With','X-CSRF-Token'],
        }
    },

    esbuild: {
        jsx: 'automatic',
        jsxImportSource: 'react',
        minify: true,
        minifySyntax: true,
        treeShaking: true,
        legalComments: 'none',
        charset: 'utf8',
        drop: ['debugger'],
        ignoreAnnotations: true,
        logLevel: 'info',
        logLimit: 0,
    },
    resolve: {
        alias: {
            'ziggy-js': resolve(__dirname, 'vendor/tightenco/ziggy'),
            '@': '/resources/js',
        },
    },
    build: {
        chunkSizeWarningLimit: 1024,
        rollupOptions: {
            output: {
                manualChunks: {
                    vendor: ['react', 'react-dom'],
                    ui: ['@radix-ui/react-dialog', '@radix-ui/react-dropdown-menu'],
                    utils: ['date-fns', 'clsx']
                }
            },
        },
        assetsDir: 'assets',
        sourcemap: false,
    }
});