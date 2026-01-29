import React, { useCallback, useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import useEmblaCarousel from 'embla-carousel-react';
import Autoplay from 'embla-carousel-autoplay';
import AnimatedCard from './AnimatedCard';

const benefits = [
  {
    icon: '\u{1F331}',
    title: 'Gestión Inteligente de Cultivos',
    description: 'Monitoreo en tiempo real de cada árbol de tu plantación de aguacate Hass.',
    iconBg: 'linear-gradient(135deg, #e8f5e9, #c8e6c9)',
  },
  {
    icon: '\u{1F4CA}',
    title: 'Decisiones Basadas en Datos',
    description: 'Analytics avanzados para maximizar tu producción y reducir pérdidas.',
    iconBg: 'linear-gradient(135deg, #fff3e0, #ffe0b2)',
  },
  {
    icon: '\u{1F50D}',
    title: 'Trazabilidad Completa',
    description: 'Desde la semilla hasta la exportación, cada paso documentado.',
    iconBg: 'linear-gradient(135deg, #e3f2fd, #bbdefb)',
  },
  {
    icon: '\u{1F33E}',
    title: 'Optimiza Cada Cosecha',
    description: 'Predicciones precisas y alertas inteligentes para tu finca.',
    iconBg: 'linear-gradient(135deg, #fce4ec, #f8bbd0)',
  },
];

const CarouselBenefits: React.FC = () => {
  const [selectedIndex, setSelectedIndex] = useState(0);
  const [emblaRef, emblaApi] = useEmblaCarousel(
    { loop: true, align: 'center' },
    [Autoplay({ delay: 4000, stopOnInteraction: false })]
  );

  const onSelect = useCallback(() => {
    if (!emblaApi) return;
    setSelectedIndex(emblaApi.selectedScrollSnap());
  }, [emblaApi]);

  useEffect(() => {
    if (!emblaApi) return;
    emblaApi.on('select', onSelect);
    onSelect();
    return () => {
      emblaApi.off('select', onSelect);
    };
  }, [emblaApi, onSelect]);

  const scrollTo = useCallback(
    (index: number) => emblaApi && emblaApi.scrollTo(index),
    [emblaApi]
  );

  return (
    <motion.div
      className="carousel-benefits"
      initial={{ opacity: 0, y: 30 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.6, delay: 0.3 }}
    >
      <div className="carousel-viewport" ref={emblaRef}>
        <div className="carousel-container">
          {benefits.map((benefit, index) => (
            <div className="carousel-slide" key={index}>
              <AnimatedCard
                icon={<span role="img" aria-label={benefit.title}>{benefit.icon}</span>}
                title={benefit.title}
                description={benefit.description}
                iconBg={benefit.iconBg}
                delay={0}
              />
            </div>
          ))}
        </div>
      </div>
      <div className="carousel-dots" role="tablist" aria-label="Beneficios del sistema">
        {benefits.map((_, index) => (
          <button
            key={index}
            className={`carousel-dot ${index === selectedIndex ? 'active' : ''}`}
            onClick={() => scrollTo(index)}
            role="tab"
            aria-selected={index === selectedIndex}
            aria-label={`Beneficio ${index + 1}`}
          />
        ))}
      </div>
    </motion.div>
  );
};

export default CarouselBenefits;
