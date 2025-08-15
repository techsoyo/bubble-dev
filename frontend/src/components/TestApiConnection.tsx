import React, { useState } from "react";
import { Button } from "./ui/button";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "./ui/card";
import { Separator } from "./ui/separator";
import { Alert, AlertDescription } from "./ui/alert";
import LoadingSpinner from "./ui/LoadingSpinner";
import { testBackendConnection } from "../lib/apiService";

interface ConnectionStatus {
  isLoading: boolean;
  success?: boolean;
  message?: string;
  error?: string;
  details?: Record<string, unknown>;
}

const TestApiConnection: React.FC = () => {
  const [connectionStatus, setConnectionStatus] = useState<ConnectionStatus>({
    isLoading: false
  });

  const testConnection = async () => {
    setConnectionStatus({ isLoading: true });
    try {
      // Usar la función de apiService en lugar de la lógica duplicada
      const result = await testBackendConnection();

      if (result.success) {
        setConnectionStatus({
          isLoading: false,
          success: true,
          message: result.message,
          details: { status: 'Connection successful' }
        });
      } else {
        setConnectionStatus({
          isLoading: false,
          success: false,
          error: result.message || "Error al conectar con el backend"
        });
      }
    } catch (error) {
      console.error("Error general al probar conexión:", error);
      setConnectionStatus({
        isLoading: false,
        success: false,
        error: "Error general al conectar con el servidor"
      });
    }
  };

  return (
    <div className="container mx-auto px-4 py-8 max-w-4xl">
      <Card className="w-full">
        <CardHeader>
          <CardTitle>Prueba de Conexión API</CardTitle>
          <CardDescription>
            Esta herramienta verifica la comunicación entre el frontend y el backend de la aplicación.
            Haz clic en el botón para probar la conexión con el backend.
          </CardDescription>
        </CardHeader>

        <CardContent>
          <Button
            variant="default"
            onClick={testConnection}
            disabled={connectionStatus.isLoading}
            className="mb-6"
          >
            {connectionStatus.isLoading ? (
              <span className="flex items-center">
                <LoadingSpinner size="small" />
                <span className="ml-2">Probando conexión...</span>
              </span>
            ) : (
              "Probar Conexión con Backend"
            )}
          </Button>

          {connectionStatus.success === true && (
            <Alert variant="default" className="mb-4 bg-green-50 border-green-200">
              <AlertDescription>
                ✅ {connectionStatus.message}
              </AlertDescription>
            </Alert>
          )}

          {connectionStatus.success === false && (
            <Alert variant="destructive" className="mb-4">
              <AlertDescription>
                ❌ {connectionStatus.error || "Error de conexión"}
              </AlertDescription>
            </Alert>
          )}

          {connectionStatus.details && (
            <div className="mt-6">
              <h3 className="text-lg font-medium mb-2">Detalles de la conexión:</h3>
              <Separator className="mb-4" />
              <div className="bg-slate-50 p-4 rounded-md overflow-auto max-h-[300px]">
                <pre className="text-sm">
                  {JSON.stringify(connectionStatus.details, null, 2)}
                </pre>
              </div>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
};

export default TestApiConnection;
