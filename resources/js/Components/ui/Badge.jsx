import React from 'react';
import { cn } from '@/lib/utils';

export default function Badge({
    children,
    variant = 'neutral',
    size = 'md',
    className = '',
    dot = false,
}) {
    const variants = {
        primary: "bg-[#004c68]/10 text-[#004c68] border-[#004c68]/20",
        secondary: "bg-[#ff8806]/15 text-[#e07400] border-[#ff8806]/30",
        success: "bg-emerald-50 text-emerald-700 border-emerald-200",
        warning: "bg-amber-50 text-amber-700 border-amber-200",
        info: "bg-sky-50 text-sky-700 border-sky-200",
        neutral: "bg-slate-100 text-slate-700 border-slate-200",
        dark: "bg-[#0a2533] text-white border-[#1a4961]",
    };

    const dotColors = {
        primary: "bg-[#004c68]",
        secondary: "bg-[#ff8806]",
        success: "bg-emerald-500",
        warning: "bg-amber-500",
        info: "bg-sky-500",
        neutral: "bg-slate-400",
        dark: "bg-emerald-400",
    };

    const sizes = {
        sm: "text-[11px] px-2 py-0.5 font-medium",
        md: "text-xs px-2.5 py-1 font-semibold",
        lg: "text-sm px-3.5 py-1.5 font-semibold",
    };

    return (
        <span className={cn(
            "inline-flex items-center gap-1.5 rounded-full border transition-colors",
            variants[variant],
            sizes[size],
            className
        )}>
            {dot && <span className={cn("w-1.5 h-1.5 rounded-full animate-pulse", dotColors[variant])} />}
            {children}
        </span>
    );
}
