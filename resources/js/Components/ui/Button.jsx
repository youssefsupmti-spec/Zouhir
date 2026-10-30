import React from 'react';
import { cn } from '@/lib/utils';
import { Loader2 } from 'lucide-react';

export default function Button({
    children,
    type = 'button',
    variant = 'primary',
    size = 'md',
    className = '',
    disabled = false,
    isLoading = false,
    icon: Icon,
    iconPosition = 'left',
    ...props
}) {
    const baseStyles = "relative inline-flex items-center justify-center font-medium rounded-xl transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed select-none active:scale-[0.98]";

    const variants = {
        primary: "bg-[#004c68] hover:bg-[#00384d] text-white shadow-md shadow-[#004c68]/20 focus:ring-[#004c68]",
        secondary: "bg-[#ff8806] hover:bg-[#e07400] text-white shadow-md shadow-[#ff8806]/25 focus:ring-[#ff8806]",
        outline: "border-2 border-[#004c68] text-[#004c68] hover:bg-[#004c68]/5 focus:ring-[#004c68]",
        outlineSecondary: "border-2 border-[#ff8806] text-[#ff8806] hover:bg-[#ff8806]/10 focus:ring-[#ff8806]",
        ghost: "text-slate-700 hover:text-[#004c68] hover:bg-slate-100/80 focus:ring-slate-300",
        white: "bg-white text-[#004c68] hover:bg-slate-100 shadow-md shadow-black/10 focus:ring-white",
        dark: "bg-[#0a2533] text-white hover:bg-[#0f3244] border border-[#1a4961] focus:ring-[#004c68]",
    };

    const sizes = {
        sm: "text-xs px-3 py-1.5 gap-1.5",
        md: "text-sm px-5 py-2.5 gap-2",
        lg: "text-base px-6 py-3.5 gap-2.5 font-semibold",
        icon: "p-2.5",
    };

    return (
        <button
            type={type}
            disabled={disabled || isLoading}
            className={cn(baseStyles, variants[variant], sizes[size], className)}
            {...props}
        >
            {isLoading && <Loader2 className="w-4 h-4 animate-spin mr-2" />}
            {!isLoading && Icon && iconPosition === 'left' && <Icon className="w-4 h-4" />}
            {children}
            {!isLoading && Icon && iconPosition === 'right' && <Icon className="w-4 h-4" />}
        </button>
    );
}
