import React from 'react';
import { Link } from '@inertiajs/react';
import Logo from '@/components/ui/Logo';
import { Phone, Mail, MapPin, Clock, ShieldCheck, Heart } from 'lucide-react';

export default function Footer() {
    return (
        <footer className="bg-[#05161f] text-slate-300 border-t border-[#1a4961]/50 relative overflow-hidden">
            {/* Ambient decorative glow */}
            <div className="absolute top-0 right-1/4 w-96 h-96 bg-[#004c68]/30 rounded-full blur-3xl pointer-events-none" />
            <div className="absolute bottom-0 left-10 w-80 h-80 bg-[#ff8806]/10 rounded-full blur-3xl pointer-events-none" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-12 relative z-10">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-10 lg:gap-8 pb-12 border-b border-slate-800">
                    {/* Brand Info */}
                    <div className="lg:col-span-2 space-y-4">
                        <Logo isWhite={true} showTagline={true} />
                        <p className="text-sm text-slate-400 max-w-sm leading-relaxed">
                            Ouais Express est la plateforme de livraison et logistique de nouvelle génération au Maroc. 
                            Nous propulsons la croissance des e-commerçants et entreprises grâce à des délais garantis 24/48h, 
                            un reversement CRBT express et un support dédié 7j/7.
                        </p>
                        <div className="flex items-center gap-2 text-xs text-slate-400 pt-2">
                            <ShieldCheck className="w-4 h-4 text-emerald-400" />
                            <span>Agrément Transport & Logistique Régulé au Maroc</span>
                        </div>
                    </div>

                    {/* Solutions */}
                    <div className="space-y-3">
                        <h4 className="text-white font-bold text-sm tracking-wide uppercase font-sans">
                            Solutions
                        </h4>
                        <ul className="space-y-2 text-xs text-slate-400">
                            <li><a href="#services" className="hover:text-white transition-colors">E-commerce Express 24h</a></li>
                            <li><a href="#services" className="hover:text-white transition-colors">Encaissement CRBT Garanti</a></li>
                            <li><a href="#services" className="hover:text-white transition-colors">Gestion Intelligente des Retours</a></li>
                            <li><a href="#services" className="hover:text-white transition-colors">Ramassage Quotidien Gratuit</a></li>
                            <li><a href="#services" className="hover:text-white transition-colors">Fulfillment & Stockage Hub</a></li>
                        </ul>
                    </div>

                    {/* Villes Principales */}
                    <div className="space-y-3">
                        <h4 className="text-white font-bold text-sm tracking-wide uppercase font-sans">
                            Couverture Réseau
                        </h4>
                        <ul className="space-y-2 text-xs text-slate-400">
                            <li>Casablanca & Région (Hub Central)</li>
                            <li>Rabat - Salé - Kénitra</li>
                            <li>Tanger - Tétouan (Zone Nord)</li>
                            <li>Marrakech - Safi</li>
                            <li>Fès - Meknès - Oujda (Zone Est)</li>
                            <li>Agadir & Régions Sud</li>
                        </ul>
                    </div>

                    {/* Contact & Support */}
                    <div className="space-y-3">
                        <h4 className="text-white font-bold text-sm tracking-wide uppercase font-sans">
                            Contact & Hub
                        </h4>
                        <ul className="space-y-2.5 text-xs text-slate-400">
                            <li className="flex items-start gap-2.5">
                                <MapPin className="w-4 h-4 text-[#ff8806] flex-shrink-0 mt-0.5" />
                                <span>Zone Industrielle Sidi Maârouf, Casablanca, Maroc</span>
                            </li>
                            <li className="flex items-center gap-2.5">
                                <Phone className="w-4 h-4 text-[#ff8806] flex-shrink-0" />
                                <span>+212 5 22 00 00 00 / WhatsApp Pro</span>
                            </li>
                            <li className="flex items-center gap-2.5">
                                <Mail className="w-4 h-4 text-[#ff8806] flex-shrink-0" />
                                <span>contact@ouaissexpress.com</span>
                            </li>
                            <li className="flex items-center gap-2.5">
                                <Clock className="w-4 h-4 text-[#ff8806] flex-shrink-0" />
                                <span>Lun - Sam : 08:30 - 19:30</span>
                            </li>
                        </ul>
                    </div>
                </div>

                {/* Bottom Bar */}
                <div className="pt-8 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 gap-4">
                    <p>
                        © {new Date().getFullYear()} OUAISS EXPRESS (Zouhir). Tous droits réservés.
                    </p>
                    <div className="flex items-center gap-6">
                        <span className="hover:text-slate-300 transition-colors">Politique de Confidentialité</span>
                        <span className="hover:text-slate-300 transition-colors">Conditions Générales (CGU)</span>
                        <span className="hover:text-slate-300 transition-colors">Sécurité CRBT</span>
                    </div>
                </div>
            </div>
        </footer>
    );
}
