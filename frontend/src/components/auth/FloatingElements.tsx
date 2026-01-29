import React from 'react';
import { motion } from 'framer-motion';

const leaves = [
  { emoji: '\u{1F343}', top: '8%', left: '10%', size: 28, duration: 6, delay: 0 },
  { emoji: '\u{1F951}', top: '15%', right: '12%', size: 36, duration: 7, delay: 1.2 },
  { emoji: '\u{1F33F}', top: '45%', left: '5%', size: 24, duration: 5.5, delay: 0.5 },
  { emoji: '\u{1F343}', top: '70%', right: '8%', size: 30, duration: 6.5, delay: 2 },
  { emoji: '\u{1F951}', bottom: '12%', left: '15%', size: 32, duration: 7.5, delay: 0.8 },
  { emoji: '\u{1F33F}', top: '30%', right: '20%', size: 22, duration: 5, delay: 1.5 },
  { emoji: '\u{1F343}', bottom: '25%', right: '25%', size: 26, duration: 6, delay: 2.5 },
  { emoji: '\u{1F951}', top: '55%', left: '20%', size: 20, duration: 8, delay: 0.3 },
  { emoji: '\u{1F33F}', bottom: '40%', left: '30%', size: 28, duration: 6.5, delay: 1.8 },
  { emoji: '\u{1F343}', top: '80%', right: '35%', size: 24, duration: 5, delay: 3 },
];

const FloatingElements: React.FC = () => {
  return (
    <div className="floating-elements" aria-hidden="true">
      {leaves.map((leaf, index) => (
        <motion.span
          key={index}
          className="floating-leaf"
          style={{
            top: leaf.top,
            left: leaf.left,
            right: leaf.right,
            bottom: leaf.bottom,
            fontSize: leaf.size,
          }}
          initial={{ opacity: 0, scale: 0.5 }}
          animate={{
            opacity: [0.08, 0.15, 0.08],
            y: [0, -15, 0],
            rotate: [0, 10, -10, 0],
            scale: [0.9, 1.05, 0.9],
          }}
          transition={{
            duration: leaf.duration,
            repeat: Infinity,
            delay: leaf.delay,
            ease: 'easeInOut',
          }}
        >
          {leaf.emoji}
        </motion.span>
      ))}
    </div>
  );
};

export default FloatingElements;
