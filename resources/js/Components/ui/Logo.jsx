import React from 'react';
import { Link } from '@inertiajs/react';

export default function Logo({ className = "h-10", isWhite = false, showTagline = false, iconOnly = false }) {
    const primaryColor = isWhite ? "#ffffff" : "#004c68";
    const secondaryColor = "#ff8806";
    const textColor = isWhite ? "#ffffff" : "#004c68";
    const subTextColor = isWhite ? "#94a3b8" : "#64748b";

    return (
        <Link href="/" className={`inline-flex items-center gap-3 group select-none ${className}`}>
            {/* Official OUAISS EXPRESS emblem image */}
            <div className="relative flex-shrink-0 w-10 h-10 rounded-xl overflow-hidden shadow-sm transition-transform duration-300 group-hover:scale-105 border border-slate-200/40 bg-white flex items-center justify-center p-0.5">
                <img 
                    src="/images/logo-icon.png" 
                    alt="OUAISS EXPRESS" 
                    className="w-full h-full object-contain"
                    onError={(e) => {
                        e.target.style.display = 'none';
                        e.target.nextSibling.style.display = 'flex';
                    }}
                />
                <div style={{ display: 'none' }} className="w-full h-full bg-[#004c68] items-center justify-center rounded-lg">
                    <span className="text-[#ff8806] font-black text-sm">OE</span>
                </div>
            </div>

            {!iconOnly && (
                <div className="flex flex-col">
                    <div className="flex items-center tracking-tight leading-none font-bold text-xl">
                        <span style={{ color: textColor }}>
                            OUAISS
                        </span>
                        <span className="ml-1.5" style={{ color: secondaryColor }}>
                            EXPRESS
                        </span>
                    </div>
                    {showTagline && (
                        <span 
                            className="text-[10px] uppercase font-semibold tracking-wider mt-0.5"
                            style={{ color: subTextColor }}
                        >
                            Logistique & Livraison Maroc
                        </span>
                    )}
                </div>
            )}
        </Link>
    );
}
