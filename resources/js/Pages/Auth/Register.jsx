import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import Logo from '@/components/ui/Logo';
import Button from '@/components/ui/AppButton';
import Input from '@/components/ui/AppInput';
import Badge from '@/components/ui/AppBadge';
import { 
    User, 
    Mail, 
    Phone, 
    Lock, 
    Eye, 
    EyeOff, 
    Building2, 
    ShoppingBag, 
    MapPin, 
    ArrowRight, 
    ArrowLeft,
    CheckCircle2, 
    ShieldCheck,
    Truck,
    Sparkles
} from 'lucide-react';

export default function Register() {
    const [showPassword, setShowPassword] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        phone: '',
        client_type: 'e_commerce',
        store_name: '',
        city: 'Casablanca',
        address: '',
        password: '',
        password_confirmation: '',
        terms: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post('/register');
    };

    const moroccanCities = [
        'Casablanca',
        'Rabat',
        'Marrakech',
        'Tanger',
        'Fès',
        'Agadir',
        'Meknès',
        'Oujda',
        'Kénitra',
        'Tétouan',
        'Safi',
        'El Jadida',
        'Nador',
        'Béni Mellal',
        'Mohammédia',
        'Autre Ville',
    ];

    return (
        <div className="min-h-screen grid grid-cols-1 lg:grid-cols-12 bg-white">
            <Head title="Créer un Compte Marchand - Ouais Express" />

            {/* Left Column: Visual Brand Storytelling */}
            <div className="hidden lg:flex lg:col-span-5 bg-gradient-to-br from-[#004c68] via-[#072432] to-[#05161f] text-white p-12 flex-col justify-between relative overflow-hidden">
                {/* Background Image Texture */}
                <div className="absolute inset-0 z-0 opacity-45 mix-blend-screen pointer-events-none">
                    <img 
                        src="/images/login-bg-dark.webp" 
                        alt="Réseau Logistique OUAISS EXPRESS Maroc" 
                        className="w-full h-full object-cover" 
                        onError={(e) => { e.target.src = '/images/login-bg-dark.jpg'; }}
                    />
                </div>

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
                        <span>Retour à l'accueil</span>
                    </Link>

                    <div className="space-y-1.5">
                        <Link href="/" className="inline-block group transition-transform duration-200 hover:scale-[1.02]">
                            <img 
                                src="/images/logo-full-dark.png" 
                                alt="OUAISS EXPRESS" 
                                className="h-12 sm:h-14 w-auto object-contain drop-shadow-lg"
                            />
                        </Link>
                        <p className="text-[10.5px] font-semibold tracking-wider text-slate-300 uppercase select-none">
                            Logistique & Livraison Express Maroc
                        </p>
                    </div>
                </div>

                {/* Middle: Onboarding Value Props */}
                <div className="relative z-10 space-y-6 my-auto py-8">
                    <Badge variant="secondary" size="md">
                        <Sparkles className="w-3.5 h-3.5 text-[#ff8806]" />
                        <span>Compte Marchand Gratuit</span>
                    </Badge>

                    <h2 className="text-3xl font-extrabold font-sans tracking-tight leading-snug">
                        Démarrez vos expéditions dès aujourd'hui sans caution.
                    </h2>

                    <div className="space-y-4 pt-2">
                        <div className="p-4 rounded-2xl bg-white/10 backdrop-blur-md border border-white/10 space-y-1">
                            <div className="flex items-center gap-2 text-sm font-bold text-white">
                                <Truck className="w-4 h-4 text-[#ff8806]" />
                                <span>Ramassage 100% Gratuit</span>
                            </div>
                            <p className="text-xs text-slate-300">
                                Un livreur dédié passe collecter vos commandes à votre dépôt ou domicile dès 1 colis.
                            </p>
                        </div>

                        <div className="p-4 rounded-2xl bg-white/10 backdrop-blur-md border border-white/10 space-y-1">
                            <div className="flex items-center gap-2 text-sm font-bold text-white">
                                <CheckCircle2 className="w-4 h-4 text-emerald-400" />
                                <span>Remboursement CRBT à J+2</span>
                            </div>
                            <p className="text-xs text-slate-300">
                                Vos encaissements sont versés directement sur votre compte bancaire chaque mardi et vendredi.
                            </p>
                        </div>
                    </div>
                </div>

                {/* Bottom: Security Notice */}
                <div className="relative z-10 pt-6 border-t border-slate-700/60 flex items-center justify-between text-xs text-slate-400">
                    <div className="flex items-center gap-2">
                        <ShieldCheck className="w-4 h-4 text-emerald-400" />
                        <span>Plateforme régulée et sécurisée</span>
                    </div>
                    <span>Support dédié 7j/7</span>
                </div>
            </div>

            {/* Right Column: Registration Form */}
            <div className="lg:col-span-7 flex flex-col justify-between p-6 sm:p-12 lg:p-14 overflow-y-auto">
                <div className="flex items-center justify-between lg:justify-end pb-6">
                    <div className="lg:hidden">
                        <Link href="/" className="inline-block">
                            <img 
                                src="/images/logo-full-light.png" 
                                alt="OUAISS EXPRESS" 
                                className="h-9 w-auto object-contain dark:hidden"
                            />
                            <img 
                                src="/images/logo-full-dark.png" 
                                alt="OUAISS EXPRESS" 
                                className="h-9 w-auto object-contain hidden dark:block"
                            />
                        </Link>
                    </div>
                    <div className="text-xs text-slate-500">
                        Déjà un compte ?{' '}
                        <Link href="/login" className="font-bold text-[#004c68] hover:text-[#ff8806]">
                            Se connecter
                        </Link>
                    </div>
                </div>

                <div className="max-w-xl w-full mx-auto my-auto space-y-7">
                    {/* Header */}
                    <div className="space-y-1.5">
                        <h1 className="text-2xl sm:text-3xl font-extrabold text-[#004c68] font-sans tracking-tight">
                            Création de Compte Marchand
                        </h1>
                        <p className="text-xs sm:text-sm text-slate-500">
                            Rejoignez le réseau Ouais Express et commencez à expédier en quelques minutes.
                        </p>
                    </div>

                    {/* Account Type Selector Tabs */}
                    <div className="space-y-2">
                        <label className="block text-xs font-bold uppercase tracking-wider text-slate-600">
                            Votre Activité
                        </label>
                        <div className="grid grid-cols-3 gap-2.5">
                            {[
                                { id: 'e_commerce', label: 'E-commerce', icon: ShoppingBag },
                                { id: 'entreprise', label: 'Entreprise / B2B', icon: Building2 },
                                { id: 'particulier', label: 'Particulier', icon: User },
                            ].map((type) => {
                                const Icon = type.icon;
                                const isSelected = data.client_type === type.id;
                                return (
                                    <button
                                        key={type.id}
                                        type="button"
                                        onClick={() => setData('client_type', type.id)}
                                        className={`flex flex-col items-center justify-center p-3 rounded-2xl border text-center transition-all ${
                                            isSelected
                                                ? 'bg-[#004c68]/10 border-[#004c68] text-[#004c68] ring-2 ring-[#004c68]/20 font-bold'
                                                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'
                                        }`}
                                    >
                                        <Icon className="w-4 h-4 mb-1" />
                                        <span className="text-xs">{type.label}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {/* Registration Form */}
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <Input
                                label="Nom Complet ou Responsable"
                                required={true}
                                icon={User}
                                placeholder="Ex: Zakaria / Zouhir ..."
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                error={errors.name}
                            />

                            <Input
                                label="Nom de la Boutique / Marque"
                                icon={ShoppingBag}
                                placeholder="Ex: AtlasStore, MoroccanShop..."
                                value={data.store_name}
                                onChange={(e) => setData('store_name', e.target.value)}
                                error={errors.store_name}
                            />
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <Input
                                label="Adresse Email Professionnelle"
                                type="email"
                                required={true}
                                icon={Mail}
                                placeholder="contact@votre-boutique.ma"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                error={errors.email}
                            />

                            <Input
                                label="Téléphone (WhatsApp Pro)"
                                required={true}
                                icon={Phone}
                                placeholder="06 00 00 00 00"
                                value={data.phone}
                                onChange={(e) => setData('phone', e.target.value)}
                                error={errors.phone}
                                helperText="Sera utilisé pour coordonner les ramassages"
                            />
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-xs font-semibold text-slate-700 tracking-wide mb-1.5">
                                    Ville de Ramassage <span className="text-amber-600">*</span>
                                </label>
                                <div className="relative">
                                    <MapPin className="w-5 h-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                                    <select
                                        value={data.city}
                                        onChange={(e) => setData('city', e.target.value)}
                                        className="w-full rounded-xl border border-slate-200 bg-white pl-11 pr-4 py-2.5 text-sm text-slate-800 focus:border-[#004c68] focus:ring-2 focus:ring-[#004c68]/20 focus:outline-none"
                                    >
                                        {moroccanCities.map((city) => (
                                            <option key={city} value={city}>
                                                {city}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                {errors.city && (
                                    <p className="text-xs font-medium text-red-600 mt-1">
                                        {errors.city}
                                    </p>
                                )}
                            </div>

                            <Input
                                label="Adresse du Stock / Dépôt (Optionnel)"
                                placeholder="Quartier, N° rue..."
                                value={data.address}
                                onChange={(e) => setData('address', e.target.value)}
                                error={errors.address}
                            />
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="relative">
                                <Input
                                    label="Mot de passe"
                                    type={showPassword ? 'text' : 'password'}
                                    required={true}
                                    icon={Lock}
                                    placeholder="Minimum 8 caractères"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    error={errors.password}
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

                            <Input
                                label="Confirmer le mot de passe"
                                type={showPassword ? 'text' : 'password'}
                                required={true}
                                icon={Lock}
                                placeholder="Répéter le mot de passe"
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                error={errors.password_confirmation}
                            />
                        </div>

                        {/* Terms and conditions */}
                        <div className="pt-2">
                            <label className="flex items-start gap-2.5 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    checked={data.terms}
                                    onChange={(e) => setData('terms', e.target.checked)}
                                    className="w-4 h-4 mt-0.5 rounded text-[#ff8806] focus:ring-[#ff8806] border-slate-300"
                                />
                                <span className="text-xs text-slate-600 leading-relaxed">
                                    J'accepte les conditions générales d'utilisation de <strong>Ouais Express</strong> ainsi que la politique de reversement CRBT.
                                </span>
                            </label>
                            {errors.terms && (
                                <p className="text-xs font-medium text-red-600 mt-1">
                                    {errors.terms}
                                </p>
                            )}
                        </div>

                        {/* Submit */}
                        <div className="pt-3">
                            <Button
                                type="submit"
                                variant="secondary"
                                size="lg"
                                className="w-full font-bold text-base shadow-xl shadow-[#ff8806]/20"
                                isLoading={processing}
                            >
                                <span>Créer Mon Compte Marchand</span>
                                <ArrowRight className="w-4 h-4 ml-2" />
                            </Button>
                        </div>
                    </form>
                </div>

                <div className="pt-6 text-center text-xs text-slate-400">
                    © {new Date().getFullYear()} OUAISS EXPRESS (Zouhir). Tous droits réservés.
                </div>
            </div>
        </div>
    );
}
