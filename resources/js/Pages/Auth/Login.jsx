import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import Logo from '@/Components/ui/Logo';
import Button from '@/Components/ui/Button';
import Input from '@/Components/ui/Input';
import Badge from '@/Components/ui/Badge';
import { 
    Mail, 
    Lock, 
    Eye, 
    EyeOff, 
    ArrowRight, 
    ShieldCheck, 
    CheckCircle2, 
    ArrowLeft,
    AlertCircle,
    Truck
} from 'lucide-react';

export default function Login({ status }) {
    const [showPassword, setShowPassword] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post('/login', {
            onFinish: () => reset('password'),
        });
    };

    return (
        <div className="min-h-screen grid grid-cols-1 lg:grid-cols-12 bg-white">
            <Head title="Connexion Espace Client - Ouais Express" />

            {/* Left Column: Visual Brand Storytelling */}
            <div className="hidden lg:flex lg:col-span-5 bg-gradient-to-br from-[#004c68] via-[#072432] to-[#05161f] text-white p-12 flex-col justify-between relative overflow-hidden">
                {/* Decorative glowing backdrops */}
                <div className="absolute top-0 right-0 w-80 h-80 bg-[#ff8806]/15 rounded-full blur-3xl pointer-events-none" />
                <div className="absolute bottom-10 left-10 w-80 h-80 bg-[#004c68]/30 rounded-full blur-3xl pointer-events-none" />

                {/* Top: Logo & Back Link */}
                <div className="relative z-10 space-y-8">
                    <Link 
                        href="/" 
                        className="inline-flex items-center gap-2 text-xs font-semibold text-slate-300 hover:text-white transition-colors"
                    >
                        <ArrowLeft className="w-4 h-4" />
                        <span>Retour au site principal</span>
                    </Link>

                    <div>
                        <Logo isWhite={true} showTagline={true} />
                    </div>
                </div>

                {/* Middle: Feature Highlights */}
                <div className="relative z-10 space-y-6 my-auto py-12">
                    <Badge variant="secondary" size="md">
                        Portail Marchand 2026
                    </Badge>
                    <h2 className="text-3xl font-extrabold font-['Outfit'] tracking-tight leading-snug">
                        Gérez vos expéditions nationales avec précision et sérénité.
                    </h2>
                    <ul className="space-y-3.5 text-xs text-slate-300">
                        <li className="flex items-center gap-2.5">
                            <div className="w-5 h-5 rounded-full bg-[#ff8806]/20 text-[#ff8806] flex items-center justify-center flex-shrink-0">
                                <CheckCircle2 className="w-3.5 h-3.5" />
                            </div>
                            <span>Suivi en direct de chaque colis avec statut horodaté</span>
                        </li>
                        <li className="flex items-center gap-2.5">
                            <div className="w-5 h-5 rounded-full bg-[#ff8806]/20 text-[#ff8806] flex items-center justify-center flex-shrink-0">
                                <CheckCircle2 className="w-3.5 h-3.5" />
                            </div>
                            <span>Relevé détaillé et historique de vos encaissements CRBT</span>
                        </li>
                        <li className="flex items-center gap-2.5">
                            <div className="w-5 h-5 rounded-full bg-[#ff8806]/20 text-[#ff8806] flex items-center justify-center flex-shrink-0">
                                <CheckCircle2 className="w-3.5 h-3.5" />
                            </div>
                            <span>Génération et impression thermique instantanée des bordereaux</span>
                        </li>
                    </ul>
                </div>

                {/* Bottom: Security Notice */}
                <div className="relative z-10 pt-6 border-t border-slate-700/60 flex items-center justify-between text-xs text-slate-400">
                    <div className="flex items-center gap-2">
                        <ShieldCheck className="w-4 h-4 text-emerald-400" />
                        <span>Chiffrement SSL 256 bits conforme</span>
                    </div>
                    <span>Ouais Express v1.0</span>
                </div>
            </div>

            {/* Right Column: Clean Login Form */}
            <div className="lg:col-span-7 flex flex-col justify-between p-6 sm:p-12 lg:p-16">
                <div className="flex items-center justify-between lg:justify-end pb-8">
                    <div className="lg:hidden">
                        <Logo />
                    </div>
                    <div className="text-xs text-slate-500">
                        Nouveau marchand ?{' '}
                        <Link href="/register" className="font-bold text-[#ff8806] hover:text-[#e07400]">
                            Créer un compte
                        </Link>
                    </div>
                </div>

                <div className="max-w-md w-full mx-auto my-auto space-y-8">
                    {/* Header */}
                    <div className="space-y-2">
                        <h1 className="text-3xl font-extrabold text-[#004c68] font-['Outfit'] tracking-tight">
                            Connexion Espace Client
                        </h1>
                        <p className="text-sm text-slate-500">
                            Accédez à votre tableau de bord Ouais Express et gérez vos expéditions.
                        </p>
                    </div>

                    {status && (
                        <div className="p-3.5 bg-emerald-50 text-emerald-700 text-xs font-medium rounded-xl border border-emerald-100 flex items-center gap-2">
                            <CheckCircle2 className="w-4 h-4" />
                            <span>{status}</span>
                        </div>
                    )}

                    {errors.email && (
                        <div className="p-3.5 bg-red-50 text-red-700 text-xs font-medium rounded-xl border border-red-100 flex items-center gap-2">
                            <AlertCircle className="w-4 h-4 flex-shrink-0" />
                            <span>{errors.email}</span>
                        </div>
                    )}

                    {/* Form */}
                    <form onSubmit={submit} className="space-y-5">
                        <Input
                            label="Adresse Email Professionnelle"
                            type="email"
                            required={true}
                            icon={Mail}
                            placeholder="nom@votre-boutique.ma"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            error={errors.email}
                            autoComplete="username"
                        />

                        <div className="space-y-1.5">
                            <div className="relative">
                                <Input
                                    label="Mot de passe"
                                    type={showPassword ? 'text' : 'password'}
                                    required={true}
                                    icon={Lock}
                                    placeholder="••••••••"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    error={errors.password}
                                    autoComplete="current-password"
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="absolute right-3.5 top-8 text-slate-400 hover:text-slate-600 focus:outline-none"
                                    aria-label="Basculer mot de passe"
                                >
                                    {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                                </button>
                            </div>
                        </div>

                        <div className="flex items-center justify-between text-xs">
                            <label className="flex items-center gap-2 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    checked={data.remember}
                                    onChange={(e) => setData('remember', e.target.checked)}
                                    className="w-4 h-4 rounded text-[#004c68] focus:ring-[#004c68] border-slate-300"
                                />
                                <span className="text-slate-600 font-medium">Se souvenir de moi</span>
                            </label>

                            <button
                                type="button"
                                onClick={() => alert('Veuillez contacter le support Ouais Express via WhatsApp pour réinitialiser vos accès en toute sécurité.')}
                                className="text-slate-500 hover:text-[#004c68] font-medium transition-colors"
                            >
                                Mot de passe oublié ?
                            </button>
                        </div>

                        <Button
                            type="submit"
                            variant="primary"
                            size="lg"
                            className="w-full font-bold shadow-lg shadow-[#004c68]/20"
                            isLoading={processing}
                        >
                            <span>Se Connecter</span>
                            <ArrowRight className="w-4 h-4 ml-2" />
                        </Button>
                    </form>

                    <div className="pt-4 border-t border-slate-100 text-center">
                        <p className="text-xs text-slate-500">
                            Vous n'avez pas encore de contrat d'expédition ?{' '}
                            <Link href="/register" className="font-bold text-[#ff8806] hover:underline">
                                Ouvrir un compte marchand
                            </Link>
                        </p>
                    </div>
                </div>

                <div className="pt-8 text-center text-xs text-slate-400">
                    © {new Date().getFullYear()} Ouais Express Maroc. Tous droits réservés.
                </div>
            </div>
        </div>
    );
}
