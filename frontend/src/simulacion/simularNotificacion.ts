// Simulación de notificación automática (ej: email)
export function simularNotificacionEmail(destinatario: string, tipo: 'registro' | 'estado', extra?: string) {
    if (tipo === 'registro') {
        window.alert(`Se ha enviado un email de confirmación a ${destinatario} (simulado).`);
    } else if (tipo === 'estado') {
        window.alert(`Se ha notificado a ${destinatario} sobre el cambio de estado: ${extra} (simulado).`);
    }
}
