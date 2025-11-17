export type ConfigProps = {
  Sidebar_drawer: boolean;
  mini_sidebar: boolean;
  actTheme: string;
  fontTheme: string;
};

const config: ConfigProps = {
    Sidebar_drawer: true,
    Customizer_drawer: false,
    mini_sidebar: false,
    setHorizontalLayout: false,
    actTheme: 'MINI_WALLET',
    fontTheme: 'Roboto 2',
    inputBg: false,
    boxed: false,
    isRtl: false
};              

export default config;
