import React from 'react';
import { Navigate } from 'react-router-dom';
import { getAuthToken } from '../services/api';

interface PrivateRouteProps {
  children: React.ReactElement;
}

const PrivateRoute: React.FC<PrivateRouteProps> = ({ children }) => {
  const token = getAuthToken();

  if (!token) {
    // User not logged in, redirect to login page
    return <Navigate to="/login" replace />;
  }

  return children;
};

export default PrivateRoute;
