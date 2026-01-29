import React, { useEffect, useState } from 'react';
import { motion, useInView } from 'framer-motion';
import { useRef } from 'react';

interface Stat {
  value: number;
  suffix: string;
  label: string;
}

const stats: Stat[] = [
  { value: 120, suffix: '+', label: 'Productores' },
  { value: 850, suffix: '', label: 'Hectáreas' },
  { value: 3200, suffix: 't', label: 'Toneladas' },
  { value: 98, suffix: '%', label: 'Éxito' },
];

const CountUp: React.FC<{ target: number; suffix: string; inView: boolean }> = ({
  target,
  suffix,
  inView,
}) => {
  const [count, setCount] = useState(0);

  useEffect(() => {
    if (!inView) return;
    let start = 0;
    const duration = 2000;
    const step = target / (duration / 16);
    const timer = setInterval(() => {
      start += step;
      if (start >= target) {
        setCount(target);
        clearInterval(timer);
      } else {
        setCount(Math.floor(start));
      }
    }, 16);
    return () => clearInterval(timer);
  }, [target, inView]);

  const formatted = target >= 1000
    ? `${(count / 1000).toFixed(count >= target ? 1 : 1)}k`
    : `${count}`;

  return (
    <span>
      {formatted}
      {suffix}
    </span>
  );
};

const StatsCounter: React.FC = () => {
  const ref = useRef<HTMLDivElement>(null);
  const inView = useInView(ref, { once: true, amount: 0.5 });

  return (
    <motion.div
      ref={ref}
      className="stats-grid"
      initial={{ opacity: 0, y: 20 }}
      animate={inView ? { opacity: 1, y: 0 } : {}}
      transition={{ duration: 0.6, delay: 0.4 }}
    >
      {stats.map((stat, index) => (
        <motion.div
          key={stat.label}
          className="stat-item"
          initial={{ opacity: 0, scale: 0.8 }}
          animate={inView ? { opacity: 1, scale: 1 } : {}}
          transition={{ duration: 0.4, delay: 0.5 + index * 0.1 }}
        >
          <p className="stat-value">
            <CountUp target={stat.value} suffix={stat.suffix} inView={inView} />
          </p>
          <span className="stat-label">{stat.label}</span>
        </motion.div>
      ))}
    </motion.div>
  );
};

export default StatsCounter;
