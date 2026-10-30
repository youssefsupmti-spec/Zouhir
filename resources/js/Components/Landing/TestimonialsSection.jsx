import React from 'react';
import { Card } from '@/Components/ui/Card';
import Badge from '@/Components/ui/Badge';
import { Star, Quote, MapPin } from 'lucide-react';

export default function TestimonialsSection({ testimonials = [] }) {
    return (
        <section id="temoignages" className="py-24 bg-white relative">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {/* Header */}
                <div className="text-center max-w-3xl mx-auto mb-16">
                    <Badge variant="primary" size="md" className="mb-3">
                        Retours d'Expérience
                    </Badge>
                    <h2 className="text-3xl sm:text-4xl font-extrabold text-[#004c68] font-['Outfit'] tracking-tight">
                        Ils développent leur marque avec Ouais Express
                    </h2>
                    <p className="mt-3 text-slate-600 text-sm sm:text-base">
                        Plus de 450 marques et boutiques en ligne marocaines nous font confiance chaque jour.
                    </p>
                </div>

                {/* Grid */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
                    {testimonials.map((item, idx) => (
                        <Card 
                            key={idx} 
                            hoverEffect={true} 
                            className="p-8 rounded-3xl border-slate-200/90 flex flex-col justify-between relative bg-slate-50/60"
                        >
                            <Quote className="w-8 h-8 text-[#ff8806]/20 absolute top-6 right-6 pointer-events-none" />

                            <div className="space-y-4">
                                {/* Stars */}
                                <div className="flex items-center gap-1">
                                    {[...Array(item.rating || 5)].map((_, i) => (
                                        <Star key={i} className="w-4 h-4 fill-amber-400 text-amber-400" />
                                    ))}
                                </div>

                                <p className="text-sm text-slate-700 leading-relaxed italic">
                                    « {item.quote} »
                                </p>
                            </div>

                            <div className="pt-6 mt-6 border-t border-slate-200/80 flex items-center gap-3.5">
                                <div className="w-11 h-11 rounded-full bg-[#004c68] text-[#ff8806] flex items-center justify-center font-bold text-sm font-['Outfit'] shadow-xs flex-shrink-0">
                                    {item.avatar}
                                </div>
                                <div>
                                    <h4 className="text-sm font-bold text-slate-900 leading-tight">
                                        {item.name}
                                    </h4>
                                    <p className="text-xs text-slate-500 font-medium">
                                        {item.role}
                                    </p>
                                    <div className="flex items-center gap-1 text-[11px] text-slate-400 mt-0.5">
                                        <MapPin className="w-3 h-3 text-[#ff8806]" />
                                        <span>{item.city}</span>
                                    </div>
                                </div>
                            </div>
                        </Card>
                    ))}
                </div>
            </div>
        </section>
    );
}
