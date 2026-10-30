import React from 'react';
import { cn } from '@/lib/utils';

export function Card({ className, children, hoverEffect = false, ...props }) {
    return (
        <div
            className={cn(
                "rounded-2xl border border-slate-200/80 bg-white text-slate-900 shadow-sm transition-all duration-200",
                hoverEffect && "hover:shadow-md hover:border-slate-300 hover:-translate-y-0.5",
                className
            )}
            {...props}
        >
            {children}
        </div>
    );
}

export function CardHeader({ className, children, ...props }) {
    return (
        <div className={cn("flex flex-col space-y-1.5 p-6", className)} {...props}>
            {children}
        </div>
    );
}

export function CardTitle({ className, children, ...props }) {
    return (
        <h3 className={cn("font-bold text-lg text-slate-900 tracking-tight", className)} {...props}>
            {children}
        </h3>
    );
}

export function CardDescription({ className, children, ...props }) {
    return (
        <p className={cn("text-xs text-slate-500", className)} {...props}>
            {children}
        </p>
    );
}

export function CardContent({ className, children, ...props }) {
    return (
        <div className={cn("p-6 pt-0", className)} {...props}>
            {children}
        </div>
    );
}

export function CardFooter({ className, children, ...props }) {
    return (
        <div className={cn("flex items-center p-6 pt-0", className)} {...props}>
            {children}
        </div>
    );
}
