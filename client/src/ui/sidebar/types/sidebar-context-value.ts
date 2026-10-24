export type SidebarContextValue = {
    isExpanded: boolean;
    isMobileOpen: boolean;
    isHovered: boolean;
    toggleSidebar: () => void;
    toggleMobileSidebar: () => void;
    closeMobileSidebar: () => void;
    setIsHovered: (isHovered: boolean) => void;
};
