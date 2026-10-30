import React, { useEffect, useState } from 'react';
import { Sun, Moon } from 'lucide-react';

export default function ThemeToggle({ className = "" }) {
    const [theme, setTheme] = useState('light');
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        setMounted(true);
        const saved = localStorage.getItem('theme');
        if (saved) {
            setTheme(saved);
            if (saved === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            setTheme('dark');
            document.documentElement.classList.add('dark');
        }
    }, []);

    const toggleTheme = () => {
        const next = theme === 'dark' ? 'light' : 'dark';
        setTheme(next);
        localStorage.setItem('theme', next);
        if (next === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    };

    if (!mounted) {
        return (
            <div className={`w-9 h-9 rounded-xl border border-border bg-card/60 ${className}`} />
        );
    }

    const isDark = theme === 'dark';

    return (
        <button
            type="button"
            onClick={toggleTheme}
            aria-label={isDark ? "Activer le mode clair" : "Activer le mode sombre"}
            title={isDark ? "Passer en mode clair" : "Passer en mode sombre"}
            className={`
                relative p-2 rounded-xl border border-border bg-card text-foreground 
                hover:bg-muted hover:border-sidebar-primary/40 focus:outline-none focus:ring-2 focus:ring-sidebar-primary/40 
                transition-all duration-200 shadow-sm flex items-center justify-center group
                ${className}
            `}
        >
            {isDark ? (
                <Sun className="w-4 h-4 text-amber-400 group-hover:rotate-45 transition-transform duration-300" />
            ) : (
                <Moon className="w-4 h-4 text-slate-700 dark:text-slate-200 group-hover:-rotate-12 transition-transform duration-300" />
            )}
        </button>
    );
}
