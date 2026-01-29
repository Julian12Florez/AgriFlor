import React from 'react';
import { motion } from 'framer-motion';
import FloatingElements from './FloatingElements';
import CarouselBenefits from './CarouselBenefits';
import StatsCounter from './StatsCounter';

const LandingSection: React.FC = () => {
  return (
    <div className="landing-side">
      <FloatingElements />

      {/* Hero Tagline */}
      <motion.div
        className="landing-hero"
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.7, ease: 'easeOut' }}
      >
        <h1 className="landing-tagline">
          Cultiva el Futuro,{' '}
          <span style={{ color: '#6B8E23' }}>Gestiona con Inteligencia</span>
        </h1>
        <p className="landing-description">
          Tu aliado tecnológico para la agricultura de precisión.
          Control total de tu producción de aguacate Hass.
        </p>
      </motion.div>

      {/* Carousel */}
      <CarouselBenefits />

      {/* Stats */}
      <StatsCounter />
    </div>
  );
};

export default LandingSection;
