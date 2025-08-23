import * as React from 'react';
import { Card, CardContent } from '@/components/ui/card';

const AIAnalyticsDashboard: React.FC = () => {
  return (
    <div className="p-3">
      <h4 className="text-2xl font-semibold mb-4">
        Dashboard de Analytics de IA
      </h4>
      <Card>
        <CardContent className="pt-6">
          <p className="text-base">
            Dashboard en desarrollo - Funcionalidad será implementada próximamente
          </p>
        </CardContent>
      </Card>
    </div>
  );
};

export default AIAnalyticsDashboard;
