import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "../../components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "../../components/ui/tabs";
import { Badge } from "../../components/ui/badge";
import { Input } from "../../components/ui/input";
import { Button } from "../../components/ui/button";
import { getEmailHistory, clearEmailHistory } from '../../lib/emailService';
import { ChevronDown, ChevronUp, Search, X, RefreshCw, Trash2 } from 'lucide-react';
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "../../components/ui/collapsible";
import { sanitizeHtml } from '../../security/xss';

interface EmailRecord {
  timestamp: string;
  to: string;
  subject: string;
  body: string;
  isHtml?: boolean;
  success: boolean;
}

export function EmailHistoryViewer() {
  const [emailHistory, setEmailHistory] = useState<EmailRecord[]>([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [activeTab, setActiveTab] = useState('all');
  const [expandedEmail, setExpandedEmail] = useState<string | null>(null);

  // Load email history from localStorage
  const loadEmailHistory = () => {
    const history = getEmailHistory();
    setEmailHistory(history);
  };

  useEffect(() => {
    loadEmailHistory();

    // Set up interval to refresh email history every 10 seconds
    const intervalId = setInterval(() => {
      loadEmailHistory();
    }, 10000);

    return () => clearInterval(intervalId);
  }, []);

  const handleClearHistory = () => {
    if (window.confirm('Are you sure you want to clear all email history? This action cannot be undone.')) {
      clearEmailHistory();
      setEmailHistory([]);
    }
  };

  const handleSearch = (e: React.ChangeEvent<HTMLInputElement>) => {
    setSearchTerm(e.target.value);
  };

  const toggleEmailExpand = (timestamp: string) => {
    if (expandedEmail === timestamp) {
      setExpandedEmail(null);
    } else {
      setExpandedEmail(timestamp);
    }
  };

  const filteredEmails = emailHistory.filter(email => {
    const matchesSearch =
      email.to.toLowerCase().includes(searchTerm.toLowerCase()) ||
      email.subject.toLowerCase().includes(searchTerm.toLowerCase());

    if (activeTab === 'all') {
      return matchesSearch;
    }
    if (activeTab === 'success') {
      return matchesSearch && email.success;
    }
    if (activeTab === 'failed') {
      return matchesSearch && !email.success;
    }
    return false;
  });

  const formatDate = (timestamp: string) => {
    try {
      const date = new Date(timestamp);
      return date.toLocaleString();
    } catch (error) {
      return timestamp;
    }
  };

  return (
    <Card className="bg-[rgba(47,47,47,0.8)] text-white">
      <CardHeader className="pb-3">
        <div className="flex justify-between items-center">
          <div>
            <CardTitle>Email Notifications History</CardTitle>
            <CardDescription>
              Record of all email notifications sent through the system
            </CardDescription>
          </div>
          <div className="flex gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={loadEmailHistory}
            >
              <RefreshCw className="h-4 w-4 mr-1" />
              Refresh
            </Button>
            <Button
              variant="destructive"
              size="sm"
              onClick={handleClearHistory}
            >
              <Trash2 className="h-4 w-4 mr-1" />
              Clear
            </Button>
          </div>
        </div>

        <div className="flex justify-between items-center mt-4">
          <Tabs
            defaultValue="all"
            className="w-[400px]"
            value={activeTab}
            onValueChange={setActiveTab}
          >
            <TabsList>
              <TabsTrigger value="all">All</TabsTrigger>
              <TabsTrigger value="success">Success</TabsTrigger>
              <TabsTrigger value="failed">Failed</TabsTrigger>
            </TabsList>
          </Tabs>

          <div className="relative">
            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
            <Input
              type="search"
              placeholder="Search emails..."
              className="w-[200px] pl-8 pr-8"
              value={searchTerm}
              onChange={handleSearch}
            />
            {searchTerm && (
              <button
                type="button"
                onClick={() => setSearchTerm('')}
                className="absolute right-2.5 top-2.5 text-muted-foreground hover:text-foreground"
              >
                <X className="h-4 w-4" />
              </button>
            )}
          </div>
        </div>
      </CardHeader>

      <CardContent>
        {filteredEmails.length === 0 ? (
          <div className="text-center py-8 text-muted-foreground">
            {searchTerm ? 'No emails match your search' : 'No email history found'}
          </div>
        ) : (
          <div className="space-y-4">
            {filteredEmails.map((email, index) => (
              <Collapsible
                key={email.timestamp + index}
                open={expandedEmail === email.timestamp}
                onOpenChange={() => { }}
                className="border rounded-md"
              >
                <div className="flex justify-between items-center p-4">
                  <div className="flex-1">
                    <div className="font-medium">{email.subject}</div>
                    <div className="text-sm text-muted-foreground mt-1 flex items-center space-x-2">
                      <span>To: {email.to}</span>
                      <span>•</span>
                      <span>{formatDate(email.timestamp)}</span>
                    </div>
                  </div>

                  <div className="flex items-center space-x-3">
                    <Badge variant={email.success ? "default" : "destructive"}>
                      {email.success ? 'Sent' : 'Failed'}
                    </Badge>
                    <CollapsibleTrigger asChild>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => toggleEmailExpand(email.timestamp)}
                      >
                        {expandedEmail === email.timestamp ? (
                          <ChevronUp className="h-4 w-4" />
                        ) : (
                          <ChevronDown className="h-4 w-4" />
                        )}
                      </Button>
                    </CollapsibleTrigger>
                  </div>
                </div>

                <CollapsibleContent>
                  <div className="px-4 pb-4 pt-0 border-t">
                    <div className="mt-2 prose prose-sm max-w-none">
                      <h4 className="text-sm font-medium">Email Content:</h4>
                      {email.isHtml ? (
                        <div
                          className="p-4 border rounded-md bg-gray-50 overflow-auto max-h-[400px]"
                          dangerouslySetInnerHTML={{ __html: sanitizeHtml(email.body, 'BASIC_HTML') }}
                        />
                      ) : (
                        <pre className="p-4 border rounded-md bg-gray-50 overflow-auto max-h-[400px] whitespace-pre-wrap">
                          {email.body}
                        </pre>
                      )}
                    </div>
                  </div>
                </CollapsibleContent>
              </Collapsible>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}
