import React, { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Save } from 'lucide-react';
import { Task, Project, ProjectMilestone, User } from '@/types';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';

interface Props {
    isOpen: boolean;
    onClose: () => void;
    task?: Task;
    projects: Project[];
    members: User[];
    milestones?: ProjectMilestone[];
}

export default function TaskFormModal({ isOpen, onClose, task, projects, members, milestones = [] }: Props) {
    const { t } = useTranslation();
    const isEditing = !!task;
    const [formData, setFormData] = useState({
        project_id: task?.project_id?.toString() || '',
        milestone_id: task?.milestone_id?.toString() || 'none',
        title: task?.title || '',
        description: task?.description || '',
        priority: task?.priority || 'medium',
        start_date: task?.start_date || '',
        end_date: task?.end_date || '',
        assigned_to: task?.assigned_to?.id?.toString() || 'none'
    });

    const [currentMilestones, setCurrentMilestones] = useState<ProjectMilestone[]>([]);
    const [currentMembers, setCurrentMembers] = useState<User[]>([]);

    // Reset form when task changes
    useEffect(() => {
        if (task) {
            // Editing mode - populate with task data
            const project = projects.find(p => p.id === task.project_id);
            setCurrentMilestones(project?.milestones || []);
            const projectMembers = project?.members?.filter(member => member.user?.type !== 'client').map(member => member.user) || [];
            setCurrentMembers(projectMembers.length > 0 ? projectMembers : members);
            
            setFormData({
                project_id: task.project_id?.toString() || '',
                milestone_id: task.milestone_id?.toString() || 'none',
                title: task.title,
                description: task.description || '',
                priority: task.priority,
                start_date: task.start_date?.split('T')[0] || '',
                end_date: task.end_date?.split('T')[0] || '',
                assigned_to: task.assigned_to?.id?.toString() || 'none'
            });
        } else {
            // Create mode - reset to defaults
            setFormData({
                project_id: '',
                milestone_id: 'none',
                title: '',
                description: '',
                priority: 'medium',
                start_date: '',
                end_date: '',
                assigned_to: 'none'
            });
            setCurrentMilestones([]);
            setCurrentMembers([]);
        }
    }, [task, projects, members]);

    const handleProjectChange = (projectId: string) => {
        setFormData(prev => ({
            ...prev, 
            project_id: projectId, 
            milestone_id: 'none', 
            assigned_to: 'none'
        }));
        
        const project = projects.find(p => p.id.toString() === projectId);
        
        // Load project milestones
        const milestones = project?.milestones || [];
        setCurrentMilestones(milestones);
        
        // Load project members (not clients)
        const projectMembers = project?.members?.filter(member => member.user?.type !== 'client').map(member => member.user) || [];
        setCurrentMembers(projectMembers.length > 0 ? projectMembers : members);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const submitData = {
            ...formData,
            milestone_id: formData.milestone_id === 'none' ? '' : formData.milestone_id,
            assigned_to: formData.assigned_to === 'none' ? '' : formData.assigned_to
        };
        
        if (isEditing) {
            toast.loading(t('Updating task...'));
            router.put(route('tasks.update', task.id), submitData, {
                onSuccess: () => {
                    toast.dismiss();
                    onClose();
                },
                onError: () => {
                    toast.dismiss();
                    toast.error(t('Failed to update task'));
                }
            });
        } else {
            toast.loading(t('Creating task...'));
            router.post(route('tasks.store'), submitData, {
                onSuccess: () => {
                    toast.dismiss();
                    onClose();
                },
                onError: () => {
                    toast.dismiss();
                    toast.error(t('Failed to create task'));
                }
            });
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{isEditing ? t('Edit Task') : t('Create Task')}</DialogTitle>
                </DialogHeader>
                
                <form onSubmit={handleSubmit} className="space-y-4">
                    {!isEditing && (
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                {t('Project')} * <span className="text-xs text-gray-500">({t('Select to load milestones & team members')})</span>
                            </label>
                            <Select value={formData.project_id || ''} onValueChange={handleProjectChange} required>
                                <SelectTrigger>
                                    <SelectValue placeholder={t('Select a project')} />
                                </SelectTrigger>
                                <SelectContent className="z-[9999]">
                                    {projects.map((project) => (
                                        <SelectItem key={project.id} value={project.id.toString()}>
                                            {project.title}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    {/* {formData.project_id && (
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                {t('Milestone')}
                            </label>
                            <Select value={formData.milestone_id} onValueChange={(value) => setFormData({...formData, milestone_id: value})}>
                                <SelectTrigger>
                                    <SelectValue placeholder={currentMilestones.length > 0 ? t('Select a milestone (optional)') : t('No milestones available')} />
                                </SelectTrigger>
                                <SelectContent className="z-[9999]">
                                    <SelectItem value="none">{t('No milestone')}</SelectItem>
                                    {currentMilestones.map((milestone) => (
                                        <SelectItem key={milestone.id} value={milestone.id.toString()}>
                                            {milestone.title}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )} */}

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            {t('Title')} *
                        </label>
                        <Input
                            value={formData.title}
                            onChange={(e) => setFormData({...formData, title: e.target.value})}
                            placeholder={t('Enter task title')}
                            required
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            {t('Description')}
                        </label>
                        <Textarea
                            value={formData.description}
                            onChange={(e) => setFormData({...formData, description: e.target.value})}
                            placeholder={t('Describe the task...')}
                            rows={3}
                        />
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                {t('Priority')}
                            </label>
                            <Select value={formData.priority} onValueChange={(value) => setFormData({...formData, priority: value})}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent className="z-[9999]">
                                    <SelectItem value="low">{t('Low')}</SelectItem>
                                    <SelectItem value="medium">{t('Medium')}</SelectItem>
                                    <SelectItem value="high">{t('High')}</SelectItem>
                                    <SelectItem value="critical">{t('Critical')}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Assign to *
                            </label>
                            <Select
                                required={true}
                                value={formData.assigned_to} 
                                onValueChange={(value) => setFormData({...formData, assigned_to: value})}
                                disabled={!formData.project_id}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder={!formData.project_id ? "Select project first" : currentMembers.length > 0 ? "Select assignee" : "No team members available"} />
                                </SelectTrigger>
                                <SelectContent className="z-[9999]">
                                    <SelectItem value="none">Unassigned</SelectItem>
                                    {currentMembers.map((member) => (
                                        <SelectItem key={member.id} value={member.id.toString()}>
                                            {member.name} {member.type !== 'client' ? '(Team)' : '(Client)'}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Start Date
                            </label>
                            <Input
                                type="date"
                                value={formData.start_date}
                                onChange={(e) => setFormData({...formData, start_date: e.target.value})}
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Due Date
                            </label>
                            <Input
                                type="date"
                                value={formData.end_date}
                                onChange={(e) => setFormData({...formData, end_date: e.target.value})}
                            />
                        </div>
                    </div>

                    <div className="flex justify-end space-x-2 pt-4">
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit">
                            <Save className="h-4 w-4 mr-2" />
                            {isEditing ? 'Update Task' : 'Create Task'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}