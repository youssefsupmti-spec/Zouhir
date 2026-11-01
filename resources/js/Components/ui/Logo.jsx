import React from 'react';
import { Link } from '@inertiajs/react';

/**
 * Composant Logo Officiel OUAISS EXPRESS
 * 
 * Supporte :
 * - Mode Clair et Mode Sombre automatique (via Tailwind dark: ou prop isWhite/isDark)
 * - Variantes : "full" (emblème + texte officiel), "icon" (emblème seul), "badge" (squircle moderne pour sidebar/avatar)
 * - Tailles : "sm", "md", "lg", "xl" ou classe personnalisée
 */
export default function Logo({ 
    className = "", 
    isWhite = false, 
    isDark = false,
    variant = "full", // "full" | "icon" | "badge"
    iconOnly = false,
    showTagline = false,
    asLink = true,
    href = "/"
}) {
    // Si iconOnly est vrai, forcer la variante icon
    const activeVariant = iconOnly ? "icon" : variant;
    const forceDark = isWhite || isDark;

    // Définition des images selon le mode
    const logoFullLight = "/images/logo-full-light.png";
    const logoFullDark = "/images/logo-full-dark.png";
    const logoIconLight = "/images/logo-icon-light.png";
    const logoIconDark = "/images/logo-icon-dark.png";
    const logoBadge = "/images/logo-icon-rounded.png";

    // Contenu du logo selon la variante
    const renderContent = () => {
        if (activeVariant === "badge") {
            return (
                <div className="relative flex items-center justify-center shrink-0">
                    <img 
                        src={logoBadge} 
                        alt="OUAISS EXPRESS" 
                        className="h-9 w-9 object-contain rounded-lg shadow-sm"
                    />
                </div>
            );
        }

        if (activeVariant === "icon") {
            return (
                <div className="relative flex items-center justify-center shrink-0">
                    {forceDark ? (
                        <img 
                            src={logoIconDark} 
                            alt="OUAISS EXPRESS" 
                            className="h-9 w-9 object-contain"
                        />
                    ) : (
                        <>
                            <img 
                                src={logoIconLight} 
                                alt="OUAISS EXPRESS" 
                                className="h-9 w-9 object-contain dark:hidden"
                            />
                            <img 
                                src={logoIconDark} 
                                alt="OUAISS EXPRESS" 
                                className="h-9 w-9 object-contain hidden dark:block"
                            />
                        </>
                    )}
                </div>
            );
        }

        // Variante par défaut : "full" (Logo horizontal officiel)
        return (
            <div className="flex flex-col">
                <div className="relative flex items-center shrink-0">
                    {forceDark ? (
                        <img 
                            src={logoFullDark} 
                            alt="OUAISS EXPRESS" 
                            className="h-9 w-auto max-w-[190px] sm:max-w-[210px] object-contain transition-transform duration-300 group-hover:scale-[1.02]"
                        />
                    ) : (
                        <>
                            <img 
                                src={logoFullLight} 
                                alt="OUAISS EXPRESS" 
                                className="h-9 w-auto max-w-[190px] sm:max-w-[210px] object-contain transition-transform duration-300 group-hover:scale-[1.02] dark:hidden"
                            />
                            <img 
                                src={logoFullDark} 
                                alt="OUAISS EXPRESS" 
                                className="h-9 w-auto max-w-[190px] sm:max-w-[210px] object-contain transition-transform duration-300 group-hover:scale-[1.02] hidden dark:block"
                            />
                        </>
                    )}
                </div>
                {showTagline && (
                    <span 
                        className={`text-[9.5px] uppercase font-semibold tracking-wider pl-12 -mt-1 select-none ${
                            forceDark ? 'text-slate-400' : 'text-slate-500 dark:text-slate-400'
                        }`}
                    >
                        Logistique & Livraison Maroc
                    </span>
                )}
            </div>
        );
    };

    if (!asLink) {
        return (
            <div className={`inline-flex items-center gap-2 select-none ${className}`}>
                {renderContent()}
            </div>
        );
    }

    return (
        <Link 
            href={href} 
            className={`inline-flex items-center gap-2 group select-none transition-opacity hover:opacity-95 ${className}`}
        >
            {renderContent()}
        </Link>
    );
}
