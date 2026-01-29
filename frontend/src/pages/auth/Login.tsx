import React from 'react';
import LoginForm from '../../components/auth/LoginForm';
import LandingSection from '../../components/auth/LandingSection';
import './Login.css';

const Login: React.FC = () => {
  return (
    <div className="login-page">
      <LoginForm />
      <LandingSection />
    </div>
  );
};

export default Login;
