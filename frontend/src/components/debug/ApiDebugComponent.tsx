import React, { useEffect, useState } from 'react';
import { getJobs, getCultureContent, getBlogPosts } from '../../lib/apiService';

const ApiDebugComponent: React.FC = () => {
  const [debugInfo, setDebugInfo] = useState<any>({});
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const testAPIs = async () => {
      const results: any = {};

      try {
        console.log('🔍 Testing Jobs API...');
        const jobsResult = await getJobs({ limit: 3 });
        results.jobs = {
          success: true,
          count: jobsResult?.data?.length || 0,
          data: jobsResult
        };
        console.log('✅ Jobs API Success:', results.jobs);
      } catch (error) {
        console.error('❌ Jobs API Error:', error);
        results.jobs = { success: false, error: (error as Error).message };
      }

      try {
        console.log('🔍 Testing Culture API...');
        const cultureResult = await getCultureContent();
        results.culture = {
          success: true,
          count: cultureResult?.length || 0,
          data: cultureResult
        };
        console.log('✅ Culture API Success:', results.culture);
      } catch (error) {
        console.error('❌ Culture API Error:', error);
        results.culture = { success: false, error: (error as Error).message };
      }

      try {
        console.log('🔍 Testing News API...');
        const newsResult = await getBlogPosts();
        results.news = {
          success: true,
          count: newsResult?.length || 0,
          data: newsResult
        };
        console.log('✅ News API Success:', results.news);
      } catch (error) {
        console.error('❌ News API Error:', error);
        results.news = { success: false, error: (error as Error).message };
      }

      setDebugInfo(results);
      setLoading(false);
    };

    testAPIs();
  }, []);

  if (loading) {
    return <div className="p-4 bg-yellow-100 rounded">🔄 Testing APIs...</div>;
  }

  return (
    <div className="p-4 bg-gray-100 rounded mb-4">
      <h3 className="font-bold mb-2">🧪 API Debug Info</h3>
      <div className="space-y-2 text-sm">
        <div>
          Jobs: {debugInfo.jobs?.success ?
            <span className="text-green-600">✅ {debugInfo.jobs.count} items</span> :
            <span className="text-red-600">❌ {debugInfo.jobs?.error}</span>
          }
        </div>
        <div>
          Culture: {debugInfo.culture?.success ?
            <span className="text-green-600">✅ {debugInfo.culture.count} items</span> :
            <span className="text-red-600">❌ {debugInfo.culture?.error}</span>
          }
        </div>
        <div>
          News: {debugInfo.news?.success ?
            <span className="text-green-600">✅ {debugInfo.news.count} items</span> :
            <span className="text-red-600">❌ {debugInfo.news?.error}</span>
          }
        </div>
      </div>
    </div>
  );
};

export default ApiDebugComponent;
