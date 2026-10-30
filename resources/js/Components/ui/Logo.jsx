import React from 'react';
import { Link } from '@inertiajs/react';

export default function Logo({ className = "h-10", isWhite = false, showTagline = false }) {
    const primaryColor = isWhite ? "#ffffff" : "#004c68";
    const secondaryColor = "#ff8806";
    const textColor = isWhite ? "#ffffff" : "#004c68";
    const subTextColor = isWhite ? "#94a3b8" : "#64748b";

    return (
        <Link href="/" className={`inline-flex items-center gap-3.5 group select-none ${className}`}>
            {/* Custom geometric logo emblem */}
            <div className="relative flex-shrink-0 w-11 h-11 flex items-center justify-center">
                <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" className="w-full h-full drop-shadow-sm transition-transform duration-300 group-hover:scale-105">
                    {/* Background badge */}
                    <rect width="48" height="48" rx="12" fill={isWhite ? "rgba(255,255,255,0.12)" : "#004c68"} />
                    
                    {/* Stylized fast delivery arrows / O letter */}
                    <path 
                        d="M14 24L26 12L23 22H34L22 36L25 26H14Z" 
                        fill="#ff8806" 
                    />
                    <path 
                        d="M26 14L34 22L31 24L24 17L26 14Z" 
                        fill="#ffffff" 
                        opacity="0.85" 
                    />
                    {/* Speed dots */}
                    <circle cx="12" cy="18" r="2" fill="#ff8806" opacity="0.8" />
                    <circle cx="10" cy="24" r="1.5" fill="#ff8806" opacity="0.6" />
                </svg>
            </div>

            <div className="flex flex-col">
                <div className="flex items-center tracking-tight leading-none">
                    <span 
                        className="font-extrabold text-xl font-['Outfit'] uppercase"
                        style={{ color: textColor }}
                    >
                        OUAISS
                    </span>
                    <span 
                        className="font-black text-xl font-['Outfit'] uppercase ml-1.5"
                        style={{ color: secondaryColor }}
                    >
                        EXPRESS
                    </span>
                </div>
                {showTagline && (
                    <span 
                        className="text-[10px] uppercase font-semibold tracking-widest mt-0.5"
                        style={{ color: subTextColor }}
                    >
                        Logistique & Livraison 24/48h
                    </span>
                )}
            </div>
        </Link>
    );
}
