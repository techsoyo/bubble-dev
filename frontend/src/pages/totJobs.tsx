import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';

import { getJobs } from '../services/ApiService';
import { Dialog, DialogTrigger, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter, DialogClose } from '../components/ui/dialog';
import { Button } from '../components/ui/button';
import CandidateApplicationForm from '../components/CandidateApplicationForm';

interface Job {
    id: string;
    title: string;
    location: string;
    type: string;
    category: string;
    description: string;
    about?: string;
    responsibilities?: string[];
    requirements?: string[];
    benefits?: string[];
}

function JobCardSmall({ job, onClick }: { job: Job, onClick: () => void }) {
    return (
        <div className="border rounded p-4 flex flex-col gap-2 bg-white shadow-sm cursor-pointer hover:shadow-md transition" onClick={onClick}>
            <div className="font-semibold text-[#2F2F2F]">{job.title}</div>
            <div className="text-xs text-gray-500">{job.location} • {job.type}</div>
            <div className="text-xs text-blue-700 bg-blue-50 rounded px-2 py-1 inline-block w-fit">{job.category}</div>
            <div className="text-xs text-gray-700 line-clamp-2">{job.description}</div>
        </div>
    );
}

export default function TotJobs() {
    const [jobs, setJobs] = useState<Job[]>([]);
    const [selectedJob, setSelectedJob] = useState<Job | null>(null);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const navigate = useNavigate();

    // MVP: simple auth flag (replace with real auth in production)
    const isAuthenticated = false;

    useEffect(() => {
        const loadJobs = async () => {
            try {
                setLoading(true);
                const jobsData = await getJobs();
                setJobs(jobsData);
            } catch (err) {
                console.error('Error loading jobs:', err);
                setError('Error al cargar los trabajos');
            } finally {
                setLoading(false);
            }
        };

        loadJobs();
    }, []);

    const handleCardClick = (job: Job) => {
        setSelectedJob(job);
        setOpen(true);
    };

    const handleClose = () => {
        setOpen(false);
        setSelectedJob(null);
    };

    const handleApply = () => {
        navigate('/auth/register');
    };

    if (loading) {
        return (
            <section className="py-12">
                <div className="container mx-auto px-4">
                    <h1 className="text-2xl md:text-3xl font-bold text-[#2F2F2F] mb-8">All Job Offers</h1>
                    <div className="flex justify-center items-center min-h-[200px]">
                        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                    </div>
                </div>
            </section>
        );
    }

    if (error) {
        return (
            <section className="py-12">
                <div className="container mx-auto px-4">
                    <h1 className="text-2xl md:text-3xl font-bold text-[#2F2F2F] mb-8">All Job Offers</h1>
                    <div className="text-center text-red-600 min-h-[200px] flex items-center justify-center">
                        <p>{error}</p>
                    </div>
                </div>
            </section>
        );
    }

    return (
        <>
            <section className="py-12">
                <div className="container mx-auto px-4">
                    <h1 className="text-2xl md:text-3xl font-bold text-[#2F2F2F] mb-8">All Job Offers</h1>
                    {jobs.length === 0 ? (
                        <div className="text-center text-gray-600 min-h-[200px] flex items-center justify-center">
                            <p>No hay trabajos disponibles en este momento.</p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            {jobs.map(job => (
                                <JobCardSmall key={job.id} job={job} onClick={() => handleCardClick(job)} />
                            ))}
                        </div>
                    )}
                </div>
            </section>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-w-2xl w-full max-h-[90vh] sm:max-h-[80vh] overflow-y-auto">
                    {selectedJob && (
                        <>
                            <DialogHeader>
                                <DialogTitle>{selectedJob.title}</DialogTitle>
                                <DialogDescription>
                                    <div className="text-sm text-gray-500 mb-2">{selectedJob.location} • {selectedJob.type}</div>
                                    <div className="text-xs text-blue-700 bg-blue-50 rounded px-2 py-1 inline-block w-fit mb-2">{selectedJob.category}</div>
                                </DialogDescription>
                            </DialogHeader>
                            <div className="my-4 text-gray-800 whitespace-pre-line">
                                {selectedJob.about && (
                                    <div className="mb-4">
                                        <span className="font-semibold">About this job:</span>
                                        <div>{selectedJob.about}</div>
                                    </div>
                                )}
                                {selectedJob.responsibilities && (
                                    <div className="mb-4">
                                        <span className="font-semibold">Responsibilities:</span>
                                        <ul className="list-disc list-inside ml-2 mt-1">
                                            {selectedJob.responsibilities.map((item, idx) => (
                                                <li key={idx}>{item}</li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                                {selectedJob.requirements && (
                                    <div className="mb-4">
                                        <span className="font-semibold">Requirements:</span>
                                        <ul className="list-disc list-inside ml-2 mt-1">
                                            {selectedJob.requirements.map((item, idx) => (
                                                <li key={idx}>{item}</li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                                {selectedJob.benefits && (
                                    <div className="mb-4">
                                        <span className="font-semibold">Benefits:</span>
                                        <ul className="list-disc list-inside ml-2 mt-1">
                                            {selectedJob.benefits.map((item, idx) => (
                                                <li key={idx}>{item}</li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                                {/* Fallback to description if no extra details */}
                                {!selectedJob.about && !selectedJob.responsibilities && !selectedJob.requirements && !selectedJob.benefits && (
                                    <div>{selectedJob.description}</div>
                                )}
                            </div>
                            <DialogFooter>
                                <div className="w-full text-center text-xs text-gray-500 mb-2">
                                    To apply for this job, you must be registered or logged in.
                                </div>
                                <CandidateApplicationForm />
                                <Button className="bg-primary hover:bg-primary-hover" onClick={handleApply}>Inscribirse</Button>
                                <DialogClose asChild>
                                    <Button variant="outline" onClick={handleClose}>Volver</Button>
                                </DialogClose>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
