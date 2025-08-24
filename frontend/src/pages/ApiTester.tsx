import { useState } from 'react';

interface Header {
  key: string;
  value: string;
}


interface Header {
  key: string;
  value: string;
}

const defaultHeaders: Header[] = [
  { key: 'Content-Type', value: 'application/json' },
];
export default function ApiTester() {
  const [url, setUrl] = useState('');
  const [method, setMethod] = useState('GET');
  const [headers, setHeaders] = useState(defaultHeaders);
  const [body, setBody] = useState('');
  const [response, setResponse] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleHeaderChange = (index: number, field: 'key' | 'value', value: string) => {
    setHeaders((prev) => {
      const newHeaders = [...prev];
      const oldHeader = newHeaders[index];
      if (!oldHeader) return newHeaders;
      newHeaders[index] = {
        key: field === 'key' ? value : oldHeader.key,
        value: field === 'value' ? value : oldHeader.value,
      };
      return newHeaders;
    });
  };

  const addHeader = () => {
    setHeaders([...headers, { key: '', value: '' }]);
  };

  const removeHeader = (index: number) => {
    setHeaders((prev) => prev.filter((_, i) => i !== index));
  };

  const handleSend = async () => {
    setLoading(true);
    setError('');
    setResponse('');
    try {
      const fetchHeaders: Record<string, string> = {};
      headers.forEach((h) => {
        if (h.key) fetchHeaders[h.key] = h.value;
      });
      const options: RequestInit = {
        method,
        headers: fetchHeaders,
      };
      if (method !== 'GET' && method !== 'HEAD' && body) {
        (options as RequestInit).body = body;
      }
      const res = await fetch(url, options);
      const text = await res.text();
      try {
        const parsed = JSON.parse(text);
        setResponse(JSON.stringify(parsed, null, 2));
      } catch {
        setResponse(text);
      }
    } catch (err) {
      if (err instanceof Error) {
        setError(err.message);
      } else {
        setError('Error desconocido');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="max-w-3xl mx-auto p-6 bg-white rounded shadow mt-8">
      <h1 className="text-2xl font-bold mb-4">API Tester</h1>
      <div className="mb-4">
        <label className="block font-semibold mb-1">URL</label>
        <input
          className="w-full border rounded px-3 py-2"
          type="text"
          value={url}
          onChange={e => setUrl(e.target.value)}
          placeholder="https://api.example.com/endpoint"
        />
      </div>
      <div className="mb-4 flex gap-4">
        <div>
          <label className="block font-semibold mb-1">Método</label>
          <select
            className="border rounded px-3 py-2"
            value={method}
            onChange={e => setMethod(e.target.value)}
          >
            <option>GET</option>
            <option>POST</option>
            <option>PUT</option>
            <option>DELETE</option>
            <option>HEAD</option>
            <option>OPTIONS</option>
          </select>
        </div>
      </div>
      <div className="mb-4">
        <label className="block font-semibold mb-1">Headers</label>
        {headers.map((h, i) => (
          <div key={i} className="flex gap-2 mb-2">
            <input
              className="border rounded px-2 py-1 w-1/3"
              type="text"
              placeholder="Key"
              value={h.key}
              onChange={e => handleHeaderChange(i, 'key', e.target.value)}
            />
            <input
              className="border rounded px-2 py-1 w-1/2"
              type="text"
              placeholder="Value"
              value={h.value}
              onChange={e => handleHeaderChange(i, 'value', e.target.value)}
            />
            <button
              className="text-red-500 font-bold px-2"
              onClick={() => removeHeader(i)}
              type="button"
            >
              ×
            </button>
          </div>
        ))}
        <button className="text-blue-500" type="button" onClick={addHeader}>+ Añadir header</button>
      </div>
      {(method !== 'GET' && method !== 'HEAD') && (
        <div className="mb-4">
          <label className="block font-semibold mb-1">Body</label>
          <textarea
            className="w-full border rounded px-3 py-2 font-mono"
            rows={5}
            value={body}
            onChange={e => setBody(e.target.value)}
            placeholder='{
  "key": "valor"
}'
          />
        </div>
      )}
      <button
        className="bg-blue-600 text-white px-6 py-2 rounded font-bold hover:bg-blue-700 disabled:opacity-50"
        onClick={handleSend}
        disabled={loading || !url}
      >
        {loading ? 'Enviando...' : 'Enviar'}
      </button>
      {error && (
        <div className="mt-4 text-red-600">{error}</div>
      )}
      <div className="mt-6">
        <label className="block font-semibold mb-1">Respuesta</label>
        <pre className="bg-gray-100 rounded p-4 overflow-x-auto min-h-[100px] max-h-[400px] text-sm">
          {response}
        </pre>
      </div>
    </div>
  );
}
