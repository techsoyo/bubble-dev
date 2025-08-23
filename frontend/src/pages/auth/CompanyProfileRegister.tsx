import { useState, useEffect } from 'react';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import ApiService from '@/services/ApiService';

// Definir perfiles disponibles para empresa
const DEFAULT_COMPANY_PROFILES = [
    'Admin',
    'RRHH',
    'Manager/Team Lead',
    'Reclutador',
];

export default function CompanyProfileRegister() {
    const [form, setForm] = useState({
        nombre: '',
        email: '',
        password: '',
        confirmPassword: '',
        perfil: '',
        telefono: '',
        avatar: '',
        notas: '',
    });
    const [passwordError, setPasswordError] = useState('');
    const [success, setSuccess] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [availableProfiles, setAvailableProfiles] = useState<string[]>(DEFAULT_COMPANY_PROFILES);
    const [error, setError] = useState('');

    // Cargar perfiles disponibles desde la API
    useEffect(() => {
        const loadAvailableProfiles = async () => {
            try {
                const response = await ApiService.get('company/available-profiles');
                if (response.success && response.data?.profiles) {
                    setAvailableProfiles(response.data.profiles);
                } else {
                    setAvailableProfiles(DEFAULT_COMPANY_PROFILES);
                }
            } catch (error) {
                console.warn('Error loading available profiles, using defaults:', error);
                setAvailableProfiles(DEFAULT_COMPANY_PROFILES);
            }
        };

        loadAvailableProfiles();
    }, []);

    const handleChange = (field: string, value: string) => setForm(f => ({ ...f, [field]: value }));

    const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        if (form.password !== form.confirmPassword) {
            setPasswordError('Las contraseñas no coinciden');
            return;
        }

        if (!form.nombre || !form.email || !form.password || !form.perfil) {
            setError('Todos los campos requeridos deben estar completos');
            return;
        }

        setPasswordError('');
        setError('');
        setIsLoading(true);

        try {
            const response = await ApiService.post('company/register-user', {
                name: form.nombre,
                email: form.email,
                password: form.password,
                profile: form.perfil,
                phone: form.telefono,
                avatar: form.avatar,
                notes: form.notas,
            });

            if (response.success) {
                setSuccess(true);
                setForm({
                    nombre: '',
                    email: '',
                    password: '',
                    confirmPassword: '',
                    perfil: '',
                    telefono: '',
                    avatar: '',
                    notas: '',
                });
            } else {
                setError(response.message || 'Error al registrar usuario');
            }
        } catch (error) {
            console.error('Error registering company user:', error);
            setError('Error al registrar usuario. Por favor, intenta de nuevo.');
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <div className="container mx-auto px-4 py-8 max-w-lg">
            <Card>
                <CardHeader>
                    <CardTitle>Registro de Usuario Interno</CardTitle>
                </CardHeader>
                <CardContent>
                    {success ? (
                        <div className="text-green-600 font-semibold text-center py-8">
                            ¡Registro exitoso!
                        </div>
                    ) : (
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <Label htmlFor="nombre">Nombre completo</Label>
                            <Input id="nombre" name="nombre" value={form.nombre} onChange={e => handleChange('nombre', e.target.value)} required placeholder="Nombre completo" />

                            <Label htmlFor="email">Email</Label>
                            <Input id="email" name="email" type="email" value={form.email} onChange={e => handleChange('email', e.target.value)} required placeholder="Email corporativo" />

                            <Label htmlFor="password">Contraseña</Label>
                            <Input id="password" name="password" type="password" value={form.password} onChange={e => handleChange('password', e.target.value)} required placeholder="Contraseña" autoComplete="new-password" />

                            <Label htmlFor="confirmPassword">Confirmar contraseña</Label>
                            <Input id="confirmPassword" name="confirmPassword" type="password" value={form.confirmPassword} onChange={e => handleChange('confirmPassword', e.target.value)} required placeholder="Repite la contraseña" autoComplete="new-password" />
                            {passwordError && <div className="text-red-500 text-sm">{passwordError}</div>}
                            {error && <div className="text-red-500 text-sm">{error}</div>}

                            <Label htmlFor="perfil">Perfil</Label>
                            <select id="perfil" name="perfil" value={form.perfil} onChange={e => handleChange('perfil', e.target.value)} required className="w-full border rounded px-3 py-2">
                                <option value="">Selecciona un perfil</option>
                                {availableProfiles.map(p => <option key={p} value={p}>{p}</option>)}
                            </select>

                            <Label htmlFor="telefono">Teléfono</Label>
                            <Input id="telefono" name="telefono" value={form.telefono} onChange={e => handleChange('telefono', e.target.value)} placeholder="Teléfono de contacto" />

                            <Label htmlFor="avatar">Avatar (URL)</Label>
                            <Input id="avatar" name="avatar" value={form.avatar} onChange={e => handleChange('avatar', e.target.value)} placeholder="URL de imagen de perfil" />

                            <Label htmlFor="notas">Notas</Label>
                            <Input id="notas" name="notas" value={form.notas} onChange={e => handleChange('notas', e.target.value)} placeholder="Notas internas (opcional)" />

                            <Button type="submit" className="bg-[#FF4785] w-full" disabled={isLoading}>
                                {isLoading ? 'Registrando...' : 'Registrar'}
                            </Button>
                        </form>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
