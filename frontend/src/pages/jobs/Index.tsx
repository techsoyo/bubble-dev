import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Button } from '../../components/ui/button';
import { Input } from '../../components/ui/input';
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '../../components/ui/select';
import { getJobs } from '../../lib/apiService';
import { Search } from 'lucide-react';

// Definir interfaz para trabajos
interface Job {
  id: string;
  title: string;
  description?: string;
  location?: string;
  employment_type?: string;
  type?: string;
  category?: string;
  created_at?: string;
}

// Definir categorías de trabajo internamente
const JOB_CATEGORIES = [
  "Desarrollo de Software",
  "Diseño UX/UI",
  "Marketing Digital",
  "Ventas",
  "Soporte Técnico",
  "Recursos Humanos",
  "Finanzas",
  "Administración",
  "Operaciones",
  "Producto"
];

export default function JobListingsPage() {
  const [searchTerm, setSearchTerm] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [jobs, setJobs] = useState<Job[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const loadJobs = async () => {
      try {
        setLoading(true);
        const response = await getJobs();
        if (response.success) {
          setJobs(response.data || []);
        } else {
          setError('Error al cargar los trabajos');
        }
      } catch (err) {
        setError('Error al conectar con el servidor');
        console.error('Error loading jobs:', err);
      } finally {
        setLoading(false);
      }
    };

    loadJobs();
  }, []);

  const filteredJobs = jobs.filter((job) => {
    const matchesSearch =
      job.title?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      job.description?.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesCategory = categoryFilter && categoryFilter !== 'all' ? job.category === categoryFilter : true;
    return matchesSearch && matchesCategory;
  });

  if (loading) {
    return (
      <div className="container mx-auto px-4 py-12 text-center">
        <p>Cargando trabajos...</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="container mx-auto px-4 py-12 text-center">
        <p className="text-red-600">{error}</p>
      </div>
    );
  }

  return (
    <>
      {/* Hero Banner */}
      <section className="text-white py-12">
        <div className="container mx-auto px-4">
          <div className="max-w-3xl">
            <h1 className="text-3xl md:text-4xl font-bold mb-4">Job Listings</h1>
            <p className="opacity-90">
              Explore our available positions and find your next career opportunity.
            </p>
          </div>
        </div>
      </section>

      {/* Search and Filter */}
      <section className="py-8">
        <div className="container mx-auto px-4">
          <div className="flex flex-col md:flex-row gap-4">
            <div className="flex-1 relative">
              <Search
                className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"
                size={18}
              />
              <Input
                className="pl-10"
                placeholder="Search by title or description"
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
              />
            </div>
            <div className="w-full md:w-64">
              <Select value={categoryFilter} onValueChange={setCategoryFilter}>
                <SelectTrigger>
                  <SelectValue placeholder="Filter by category" />
                </SelectTrigger>
                <SelectContent>
                  <SelectGroup>
                    <SelectItem value="all">All categories</SelectItem>
                    {JOB_CATEGORIES.map((category) => (
                      <SelectItem key={category} value={category}>
                        {category}
                      </SelectItem>
                    ))}
                  </SelectGroup>
                </SelectContent>
              </Select>
            </div>
          </div>
        </div>
      </section>

      {/* Job Listings */}
      <section className="py-12">
        <div className="container mx-auto px-4">
          <div className="mb-6">
            <h2 className="text-xl font-semibold">
              {filteredJobs.length} {filteredJobs.length === 1 ? 'job found' : 'jobs found'}
            </h2>
          </div>

          {filteredJobs.length > 0 ? (
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              {filteredJobs.map((job) => (
                <div
                  key={job.id}
                  className="flex flex-col h-full rounded-lg border border-gray-100 bg-white hover:shadow-lg transition-shadow duration-300"
                >
                  <div className="p-4 border-b flex justify-between items-start">
                    <div>
                      <h3 className="mb-2 text-lg font-semibold">{job.title}</h3>
                      <div className="text-sm text-[#2F2F2F]">{job.location || 'Ubicación no especificada'} • {job.employment_type || job.type || 'Tipo no especificado'}</div>
                    </div>
                    <span className="inline-flex items-center rounded-full bg-blue-50 px-2 py-1 text-xs text-blue-700">
                      {job.category || 'Sin categoría'}
                    </span>
                  </div>
                  <div className="flex-grow p-4">
                    <p className="text-[#2F2F2F]">{job.description}</p>
                  </div>
                  <div className="flex justify-between items-center p-4 border-t">
                    <span className="text-sm text-[#2F2F2F]">
                      Publicado: {job.created_at ? new Date(job.created_at).toLocaleDateString() : 'Fecha no disponible'}
                    </span>
                    <Link to={`/jobs/${job.id}`}>
                      <Button className="bg-[#FF4785] hover:bg-[#FF3575]">Ver Más</Button>
                    </Link>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="text-center py-12">
              <h3 className="text-lg font-medium">No jobs found</h3>
              <p className="text-[#2F2F2F] mt-2">Try different search terms or filters</p>
            </div>
          )}
        </div>
      </section>

      {/* CTA */}
      <section className="py-12">
        <div className="container mx-auto px-4 text-center">
          <h2 className="text-2xl font-bold mb-4">Can't find what you're looking for?</h2>
          <p className="max-w-2xl mx-auto mb-6 text-[#2F2F2F]">
            Register in our database to be considered for future opportunities that match your
            profile.
          </p>
          <Link to="/candidates/login">
            <Button size="lg" className="bg-[#FF4785] hover:bg-[#FF3575]">
              Register my profile
            </Button>
          </Link>
        </div>
      </section>
    </>
  );
}
