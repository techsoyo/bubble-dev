import React from 'react';
import { Box, Typography, Card, CardContent } from '@mui/material';

const AIAnalyticsDashboard: React.FC = () => {
  return (
    <Box p={3}>
      <Typography variant="h4" gutterBottom>
        Dashboard de Analytics de IA
      </Typography>
      <Card>
        <CardContent>
          <Typography variant="body1">
            Dashboard en desarrollo - Funcionalidad será implementada próximamente
          </Typography>
        </CardContent>
      </Card>
    </Box>
  );
};

export default AIAnalyticsDashboard;
