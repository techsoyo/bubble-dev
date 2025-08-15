import { useState } from 'react';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import { Label } from '../../components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';

// Definir perfiles de empresa internamente
const MOCK_PROFILES_COMPANY = [
    { id: 1, nombre: 'Admin Principal', email: 'admin@example.com', perfil: 'Admin' },
    { id: 2, nombre: 'Responsable RRHH', email: 'rrhh@example.com', perfil: 'RRHH' },
    { id: 3, nombre: 'Reclutador 1', email: 'recruiter1@example.com', perfil: 'Reclutador' },
    { id: 4, nombre: 'Reclutador 2', email: 'recruiter2@example.com', perfil: 'Reclutador' },
    { id: 5, nombre: 'Team Lead', email: 'teamlead@example.com', perfil: 'Manager/Team Lead' }
];


// Ocultar 'Admin' si ya existe un admin en el mock
const adminExists = MOCK_PROFILES_COMPANY.some(u => u.perfil === 'Admin');
const perfiles = [
    ...(!adminExists ? ['Admin'] : []),
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

    const handleChange = (field: string, value: string) => setForm(f => ({ ...f, [field]: value }));

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (form.password !== form.confirmPassword) {
            setPasswordError('Las contraseñas no coinciden');
            return;
        }
        setPasswordError('');
        setSuccess(true);
        // Aquí iría la lógica de registro real o mock
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

                            <Label htmlFor="perfil">Perfil</Label>
                            <select id="perfil" name="perfil" value={form.perfil} onChange={e => handleChange('perfil', e.target.value)} required className="w-full border rounded px-3 py-2">
                                <option value="">Selecciona un perfil</option>
                                {perfiles.map(p => <option key={p} value={p}>{p}</option>)}
                            </select>

                            <Label htmlFor="telefono">Teléfono</Label>
                            <Input id="telefono" name="telefono" value={form.telefono} onChange={e => handleChange('telefono', e.target.value)} placeholder="Teléfono de contacto" />

                            <Label htmlFor="avatar">Avatar (URL)</Label>
                            <Input id="avatar" name="avatar" value={form.avatar} onChange={e => handleChange('avatar', e.target.value)} placeholder="URL de imagen de perfil" />

                            <Label htmlFor="notas">Notas</Label>
                            <Input id="notas" name="notas" value={form.notas} onChange={e => handleChange('notas', e.target.value)} placeholder="Notas internas (opcional)" />

                            <Button type="submit" className="bg-[#FF4785] w-full">Registrar</Button>
                        </form>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
