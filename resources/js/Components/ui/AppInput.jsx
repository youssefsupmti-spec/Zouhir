import React, { forwardRef } from 'react';
import { cn } from '@/lib/utils';

const Input = forwardRef(({
    label,
    error,
    helperText,
    icon: Icon,
    className = '',
    containerClassName = '',
    required = false,
    id,
    ...props
}, ref) => {
    const inputId = id || (label ? label.toLowerCase().replace(/\s+/g, '-') : undefined);

    return (
        <div className={cn("w-full space-y-1.5", containerClassName)}>
            {label && (
                <label 
                    htmlFor={inputId} 
                    className="block text-xs font-semibold text-slate-700 tracking-wide"
                >
                    {label}
                    {required && <span className="text-amber-600 ml-1">*</span>}
                </label>
            )}

            <div className="relative rounded-xl shadow-xs">
                {Icon && (
                    <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <Icon className="w-5 h-5" />
                    </div>
                )}

                <input
                    id={inputId}
                    ref={ref}
                    className={cn(
                        "w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-slate-900 transition-all duration-150 placeholder:text-slate-400",
                        "focus:border-[#004c68] focus:outline-none focus:ring-2 focus:ring-[#004c68]/20",
                        Icon && "pl-11",
                        error ? "border-red-500 focus:border-red-500 focus:ring-red-500/20" : "border-slate-200 hover:border-slate-300",
                        className
                    )}
                    {...props}
                />
            </div>

            {error && (
                <p className="text-xs font-medium text-red-600 animate-fadeIn">
                    {error}
                </p>
            )}

            {helperText && !error && (
                <p className="text-xs text-slate-500">
                    {helperText}
                </p>
            )}
        </div>
    );
});

Input.displayName = 'Input';
export default Input;
