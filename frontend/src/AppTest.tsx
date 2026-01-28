import React from 'react';

const AppTest: React.FC = () => {
  return (
    <div style={{
      padding: '20px',
      backgroundColor: '#f0f0f0',
      minHeight: '100vh',
      fontFamily: 'Arial, sans-serif'
    }}>
      <h1 style={{ color: '#2E7D32' }}>🌿 AgriFlor - Test</h1>
      <p>Si ves este mensaje, React está funcionando correctamente!</p>
      <div style={{
        backgroundColor: 'white',
        padding: '20px',
        borderRadius: '8px',
        marginTop: '20px',
        border: '1px solid #4CAF50'
      }}>
        <h2>Sistema de Gestión Agrícola</h2>
        <p>✅ React funcionando</p>
        <p>✅ Estilos aplicados</p>
        <p>✅ Componente renderizando</p>
      </div>
    </div>
  );
};

export default AppTest;