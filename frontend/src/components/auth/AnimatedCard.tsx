import React from 'react';
import { motion } from 'framer-motion';
import { Typography } from 'antd';

const { Text } = Typography;

interface AnimatedCardProps {
  icon: React.ReactNode;
  title: string;
  description: string;
  iconBg: string;
  delay?: number;
}

const AnimatedCard: React.FC<AnimatedCardProps> = ({
  icon,
  title,
  description,
  iconBg,
  delay = 0,
}) => {
  return (
    <motion.div
      className="animated-card"
      initial={{ opacity: 0, y: 30 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.5, delay, ease: 'easeOut' }}
      whileHover={{
        y: -4,
        transition: { duration: 0.2 },
      }}
    >
      <div className="animated-card-icon" style={{ background: iconBg }}>
        {icon}
      </div>
      <h4 className="animated-card-title">{title}</h4>
      <Text className="animated-card-description">{description}</Text>
    </motion.div>
  );
};

export default AnimatedCard;
