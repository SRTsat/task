import React, { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Calendar, Save, X, Plus, Trash2 } from 'lucide-react';

interface Timesheet {
    id?: number;
    start_date: string;
    end_date: string;
    notes?: string;
    entries?: TimesheetEntry[];
    created_at?: string;
    updated_at?: string;
}

interface TimesheetEntry {
    id?: number;
    project_id: string;
    task_id?: string;
    date: string;
    start_time: string;
    end_time: string;
    hours?: number;
    description: string;
    is_billable?: boolean;
    hourly_rate?: number;
    created_at?: string;
    updated_at?: string;
}

interface Project {
    id: number;
    title: string;
    tasks?: Task[];
}

interface Task {
    id: number;
    title: string;
}

interface Props {
    isOpen: boolean;
    onClose: () => void;
    timesheet?: Timesheet;
    projects?: Project[];
}

export default function TimesheetFormModal({ isOpen, onClose, timesheet, projects = [] }: Props) {
    const currentDate = new Date((new Date).toLocaleString("en-US", { timeZone: "Asia/Jakarta" })).toISOString().split('T')[0].split('-');
    const [formData, setFormData] = useState({
        start_date: '',
        end_date: '',
        notes: ''
    });
    const [entries, setEntries] = useState<TimesheetEntry[]>([]);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<string[]>([]);

    useEffect(() => {
        if (timesheet && isOpen) {
            if (timesheet.id && !timesheet.entries) {
                // Fetch full timesheet data with entries
                fetch(route('timesheets.show', timesheet.id))
                    .then(response => response.json())
                    .then(data => {
                        const startDate = window.appSettings?.formatDateForInput(data.timesheet.start_date) || '';
                        const endDate = window.appSettings?.formatDateForInput(data.timesheet.end_date) || '';
                        setFormData({
                            start_date: startDate,
                            end_date: endDate,
                            notes: data.timesheet.notes || ''
                        });
                        setEntries(data.timesheet.entries?.map((entry: any) => ({
                            id: entry.id,
                            project_id: entry.project_id?.toString() || '',
                            task_id: entry.task_id?.toString() || 'none',
                            date: entry.date || data.timesheet.start_date,
                            start_time: entry.start_time ? entry.start_time.substring(0, 5) : '',
                            end_time: entry.end_time ? entry.end_time.substring(0, 5) : '',
                            hours: entry.hours || 0,
                            description: entry.description || '',
                            is_billable: entry.is_billable || true,
                            hourly_rate: entry.hourly_rate || 0,
                            created_at: entry.created_at,
                            updated_at: entry.updated_at
                        })) || []);
                    });
            } else {
                const startDate = window.appSettings?.formatDateForInput(timesheet.start_date) || '';
                const endDate = window.appSettings?.formatDateForInput(timesheet.end_date) || '';
                setFormData({
                    start_date: startDate,
                    end_date: endDate,
                    notes: timesheet.notes || ''
                });
                setEntries(timesheet.entries?.map((entry: any) => ({
                    id: entry.id,
                    project_id: entry.project_id?.toString() || '',
                    task_id: entry.task_id?.toString() || 'none',
                    date: entry.date || timesheet.start_date,
                    start_time: entry.start_time ? entry.start_time.substring(0, 5) : '',
                    end_time: entry.end_time ? entry.end_time.substring(0, 5) : '',
                    hours: entry.hours || 0,
                    description: entry.description || '',
                    is_billable: entry.is_billable || true,
                    hourly_rate: entry.hourly_rate || 0,
                    created_at: entry.created_at,
                    updated_at: entry.updated_at
                })) || []);
            }
        } else if (isOpen) {
            const currentDate = new Date((new Date).toLocaleString("en-US", { timeZone: "Asia/Jakarta" })).toISOString().split('T')[0].split('-');
            setFormData({
                start_date: '',
                end_date: '',
                notes: ''
            });
            setEntries([]);
        }
    }, [timesheet, isOpen]);

    const addEntry = () => {
        const now = new Date();
        const startTime = new Date(now.getTime());
        const endTime = new Date(now.getTime() + 8 * 60 * 60 * 1000); // 8 hours later
        
        const newEntry: TimesheetEntry = {
            project_id: '',
            task_id: 'none',
            date: formData.start_date,
            start_time: '09:00',
            end_time: '17:00',
            hours: 0,
            description: '',
            is_billable: true,
            hourly_rate: 0
        };
        setEntries([...entries, newEntry]);
    };

    const removeEntry = (index: number) => {
        setEntries(entries.filter((_, i) => i !== index));
    };

    const updateEntry = (index: number, field: keyof TimesheetEntry, value: any) => {
        const updatedEntries = [...entries];
        updatedEntries[index] = { ...updatedEntries[index], [field]: value };
        setEntries(updatedEntries);
    };

    const getTasksForProject = (projectId: string) => {
        const project = projects.find(p => p.id.toString() === projectId);
        return project?.tasks || [];
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setErrors([]);
        
        const validationErrors: string[] = [];
        
        // Validation
        if (!formData.start_date) {
            validationErrors.push('Please select a start date');
        }
        if (!formData.end_date) {
            validationErrors.push('Please select an end date');
        }
        if (formData.start_date && formData.end_date && formData.end_date < formData.start_date) {
            validationErrors.push('End date must be after or same as start date');
        }
        if (entries.length === 0) {
            validationErrors.push('Please add at least one time entry');
        }
        
        for (let i = 0; i < entries.length; i++) {
            const entry = entries[i];
            if (!entry.project_id) {
                validationErrors.push(`Entry ${i + 1}: Please select a project`);
            }
            if (!entry.task_id) {
                validationErrors.push(`Entry ${i + 1}: Please select a task`);
            }
            if (!entry.start_time) {
                validationErrors.push(`Entry ${i + 1}: Please enter start time`);
            }
            if (!entry.end_time) {
                validationErrors.push(`Entry ${i + 1}: Please enter end time`);
            }
        }
        
        if (validationErrors.length > 0) {
            setErrors(validationErrors);
            return;
        }
        
        setIsSubmitting(true);

        const submitData = {
            ...formData,
            entries: entries.map(entry => ({
                ...entry,
                task_id: entry.task_id === 'none' ? null : entry.task_id,
                start_time: entry.start_time ? `${formData.start_date}T${entry.start_time}:00` : '',
                end_time: entry.end_time ? `${formData.start_date}T${entry.end_time}:00` : ''
            }))
        };
        
        console.log('Submitting data:', submitData);

        const url = timesheet 
            ? route('timesheets.update', timesheet.id)
            : route('timesheets.store');

        const method = timesheet ? 'put' : 'post';

        router[method](url, submitData, {
            onSuccess: () => {
                onClose();
                setFormData({ start_date: '', end_date: '', notes: '' });
                setEntries([]);
            },
            onError: (backendErrors) => {
                console.log('Backend errors:', backendErrors);
                const errorMessages = [];
                if (typeof backendErrors === 'object') {
                    Object.keys(backendErrors).forEach(key => {
                        if (Array.isArray(backendErrors[key])) {
                            errorMessages.push(...backendErrors[key]);
                        } else {
                            errorMessages.push(backendErrors[key]);
                        }
                    });
                }
                setErrors(errorMessages.length > 0 ? errorMessages : ['Error submitting timesheet. Please check all fields.']);
            },
            onFinish: () => setIsSubmitting(false)
        });
    };

    const handleChange = (field: string, value: string) => {
        setFormData(prev => ({ ...prev, [field]: value }));
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-4xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Calendar className="h-5 w-5" />
                        {timesheet ? 'Edit Timesheet' : 'New Timesheet'}
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Basic Timesheet Info */}
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label htmlFor="start_date">Start Date</Label>
                            <Input
                                id="start_date"
                                type="date"
                                value={formData.start_date}
                                onChange={(e) => handleChange('start_date', e.target.value)}
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="end_date">End Date</Label>
                            <Input
                                id="end_date"
                                type="date"
                                value={formData.end_date}
                                onChange={(e) => handleChange('end_date', e.target.value)}
                            />
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="notes">Notes</Label>
                        <Textarea
                            id="notes"
                            placeholder="Add any notes about this timesheet..."
                            value={formData.notes}
                            onChange={(e) => handleChange('notes', e.target.value)}
                            rows={2}
                        />
                    </div>

                    {errors.length > 0 && (
                        <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                            <ul className="text-red-700 text-sm space-y-1">
                                {errors.map((error, index) => (
                                    <li key={index}>• {error}</li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {/* Time Entries */}
                    <div className="space-y-4">
                        <div className="flex justify-between items-center">
                            <Label className="text-base font-semibold">Time Entries</Label>
                            <Button type="button" onClick={addEntry} size="sm">
                                <Plus className="h-4 w-4 mr-2" />
                                Add Entry
                            </Button>
                        </div>

                        {entries.map((entry, index) => (
                            <div key={index} className="border rounded-lg p-4 space-y-4">
                                <div className="flex justify-between items-center">
                                    <span className="font-medium">Entry {index + 1}</span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => removeEntry(index)}
                                        className="text-red-600 hover:text-red-700"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label>Project *</Label>
                                        <Select
                                            value={entry.project_id}
                                            onValueChange={(value) => updateEntry(index, 'project_id', value)}
                                            required
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select project" />
                                            </SelectTrigger>
                                            <SelectContent side="bottom" align="start" sideOffset={4} style={{zIndex: 999999}}>
                                                {projects.map(project => (
                                                    <SelectItem key={project.id} value={project.id.toString()}>
                                                        {project.title}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Task *</Label>
                                        <Select
                                            value={entry.task_id || 'none'}
                                            onValueChange={(value) => updateEntry(index, 'task_id', value)}
                                            required
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select task" />
                                            </SelectTrigger>
                                            <SelectContent side="bottom" align="start" sideOffset={4} style={{zIndex: 999999}}>
                                                <SelectItem value="none">No task</SelectItem>
                                                {getTasksForProject(entry.project_id).map(task => (
                                                    <SelectItem key={task.id} value={task.id.toString()}>
                                                        {task.title}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>



                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label>Start Time *</Label>
                                        <Input
                                            type="time"
                                            value={entry.start_time || ''}
                                            onChange={(e) => updateEntry(index, 'start_time', e.target.value)}
                                            required
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label>End Time *</Label>
                                        <Input
                                            type="time"
                                            value={entry.end_time || ''}
                                            onChange={(e) => updateEntry(index, 'end_time', e.target.value)}
                                            required
                                        />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label>Description</Label>
                                    <Textarea
                                        placeholder="Describe the work done..."
                                        value={entry.description}
                                        onChange={(e) => updateEntry(index, 'description', e.target.value)}
                                        rows={2}
                                    />
                                </div>
                            </div>
                        ))}

                        {entries.length === 0 && (
                            <div className="text-center py-8 text-muted-foreground">
                                <Calendar className="h-12 w-12 mx-auto mb-4 opacity-50" />
                                <p>No time entries added yet</p>
                                <Button type="button" onClick={addEntry} className="mt-2">
                                    <Plus className="h-4 w-4 mr-2" />
                                    Add First Entry
                                </Button>
                            </div>
                        )}
                    </div>

                    <div className="flex justify-end gap-2 pt-4 border-t">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                            disabled={isSubmitting}
                        >
                            <X className="h-4 w-4 mr-2" />
                            Cancel
                        </Button>
                        <Button type="submit" disabled={isSubmitting}>
                            <Save className="h-4 w-4 mr-2" />
                            {isSubmitting ? 'Saving...' : timesheet ? 'Update' : 'Create'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}