// frontend/src/components/TestMigration.tsx
import React, { useState } from 'react';
import { getCandidates, getJobs, getApplications, getRecruiters } from '../lib/apiService';

interface TestResults {
    candidates: any;
    jobs: any;
    applications: any;
    recruiters: any;
}

const TestMigration: React.FC = () => {
    const [results, setResults] = useState<TestResults>({
        candidates: null,
        jobs: null,
        applications: null,
        recruiters: null
    });
    const [loading, setLoading] = useState<{ [key: string]: boolean }>({});
    const [errors, setErrors] = useState<{ [key: string]: string }>({});

    const testAPI = async (apiName: string, apiFunction: () => Promise<any>) => {
        setLoading(prev => ({ ...prev, [apiName]: true }));
        setErrors(prev => ({ ...prev, [apiName]: '' }));

        try {
            const result = await apiFunction();
            setResults(prev => ({ ...prev, [apiName]: result }));
            console.log(`✅ ${apiName} API funcionando:`, result);
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : 'Error desconocido';
            setErrors(prev => ({ ...prev, [apiName]: errorMessage }));
            console.error(`❌ Error en ${apiName} API:`, error);
        } finally {
            setLoading(prev => ({ ...prev, [apiName]: false }));
        }
    };

    const testAllAPIs = async () => {
        await Promise.all([
            testAPI('candidates', () => getCandidates({ limit: 5 })),
            testAPI('jobs', () => getJobs({ limit: 5 })),
            testAPI('applications', () => getApplications()),
            testAPI('recruiters', () => getRecruiters({ status: 'active' }))
        ]);
    };

    const renderResult = (apiName: string, data: any) => {
        if (loading[apiName]) {
            return <div className="text-blue-600">🔄 Cargando...</div>;
        }

        if (errors[apiName]) {
            return <div className="text-red-600">❌ Error: {errors[apiName]}</div>;
        }

        if (!data) {
            return <div className="text-gray-500">Sin datos</div>;
        }

        return (
            <div className="text-green-600">
                ✅ Éxito: {data.data?.length || 0} elementos obtenidos
                <details className="mt-2">
                    <summary className="cursor-pointer text-sm text-blue-600">Ver datos</summary>
                    <pre className="mt-2 text-xs bg-gray-100 p-2 rounded overflow-x-auto">
                        {JSON.stringify(data, null, 2)}
                    </pre>
                </details>
            </div>
        );
    };

    return (
        <div className="max-w-6xl mx-auto p-6 bg-white shadow-lg rounded-lg">
            <h1 className="text-3xl font-bold mb-6 text-center">
                🚀 Test de Migración: Mock Arrays → Database APIs
            </h1>

            <div className="mb-6 text-center">
                <button
                    onClick={testAllAPIs}
                    className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg"
                    disabled={Object.values(loading).some(Boolean)}
                >
                    {Object.values(loading).some(Boolean) ? '🔄 Probando...' : '🔥 Probar Todas las APIs'}
                </button>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="border rounded-lg p-4">
                    <h2 className="text-xl font-semibold mb-3 flex items-center">
                        📋 Candidatos
                        <button
                            onClick={() => testAPI('candidates', () => getCandidates({ limit: 5 }))}
                            className="ml-auto bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm"
                            disabled={loading.candidates}
                        >
                            Probar
                        </button>
                    </h2>
                    {renderResult('candidates', results.candidates)}
                </div>

                <div className="border rounded-lg p-4">
                    <h2 className="text-xl font-semibold mb-3 flex items-center">
                        💼 Trabajos
                        <button
                            onClick={() => testAPI('jobs', () => getJobs({ limit: 5 }))}
                            className="ml-auto bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm"
                            disabled={loading.jobs}
                        >
                            Probar
                        </button>
                    </h2>
                    {renderResult('jobs', results.jobs)}
                </div>

                <div className="border rounded-lg p-4">
                    <h2 className="text-xl font-semibold mb-3 flex items-center">
                        📝 Aplicaciones
                        <button
                            onClick={() => testAPI('applications', () => getApplications())}
                            className="ml-auto bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm"
                            disabled={loading.applications}
                        >
                            Probar
                        </button>
                    </h2>
                    {renderResult('applications', results.applications)}
                </div>

                <div className="border rounded-lg p-4">
                    <h2 className="text-xl font-semibold mb-3 flex items-center">
                        👥 Reclutadores
                        <button
                            onClick={() => testAPI('recruiters', () => getRecruiters({ status: 'active' }))}
                            className="ml-auto bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm"
                            disabled={loading.recruiters}
                        >
                            Probar
                        </button>
                    </h2>
                    {renderResult('recruiters', results.recruiters)}
                </div>
            </div>

            <div className="mt-8 p-4 bg-gray-50 rounded-lg">
                <h3 className="text-lg font-semibold mb-2">📊 Estado de la Migración</h3>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div className={`p-2 rounded ${results.candidates ? 'bg-green-100 text-green-800' : 'bg-gray-100'}`}>
                        Candidatos: {results.candidates ? '✅' : '⏳'}
                    </div>
                    <div className={`p-2 rounded ${results.jobs ? 'bg-green-100 text-green-800' : 'bg-gray-100'}`}>
                        Trabajos: {results.jobs ? '✅' : '⏳'}
                    </div>
                    <div className={`p-2 rounded ${results.applications ? 'bg-green-100 text-green-800' : 'bg-gray-100'}`}>
                        Aplicaciones: {results.applications ? '✅' : '⏳'}
                    </div>
                    <div className={`p-2 rounded ${results.recruiters ? 'bg-green-100 text-green-800' : 'bg-gray-100'}`}>
                        Reclutadores: {results.recruiters ? '✅' : '⏳'}
                    </div>
                </div>
            </div>
        </div>
    );
};

export default TestMigration;
