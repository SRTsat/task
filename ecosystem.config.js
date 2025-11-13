// ecosystem.config.js

export const apps = [
    {
        name: 'sada-daily-task',
        script: 'composer dev',
        args: [],
        instances: 1,
        exec_mode: 'fork',
        autorestart: true,
        watch: false,
        max_memory_restart: '4G',
        out_file: './storage/logs/pm2.log',
        error_file: './storage/logs/pm2.log',
        restart_delay: 3600,
        merge_logs: true
    }
];