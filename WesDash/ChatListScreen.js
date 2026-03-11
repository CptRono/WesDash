import React, { useEffect, useState } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  StyleSheet,
  Alert,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { BASE_URL } from '../config';

const ChatListScreen = ({ navigation, route }) => {
  const { username: paramUser, role: paramRole } = route.params ?? {};

  const [username,  setUsername]  = useState(paramUser ?? null);
  const [role,      setRole]      = useState(paramRole ?? null);
  const [rooms,     setRooms]     = useState([]);
  const [sessionID, setSessionID] = useState(null);

  const fetchRooms = async (sid) => {
    try {
      const url = `${BASE_URL}/WesDashAPI/list_chats.php?PHPSESSID=${sid}`;
      console.log('Fetching chat rooms from:', url);
      const resp = await fetch(url, { method: 'GET' });
      const data = await resp.json();
      if (data.success) setRooms(data.rooms);
      else Alert.alert('Error', data.message || 'Failed to load chats');
    } catch (e) {
      console.error('fetchRooms error', e);
      Alert.alert('Error', 'Network error');
    }
  };

  useEffect(() => {
    (async () => {
      const sid = await AsyncStorage.getItem('PHPSESSID');
      if (!sid) { Alert.alert('Error', 'Session not found'); return; }
      setSessionID(sid);

      if (!username) setUsername(await AsyncStorage.getItem('username'));
      if (!role)     setRole(await AsyncStorage.getItem('role'));

      fetchRooms(sid);
    })();
  }, []);

  const Item = ({ item }) => (
    <TouchableOpacity
      style={styles.card}
      onPress={() =>
        navigation.navigate('Chat', {
          roomId:  item.room_id,
          username
        })
      }
    >
      <Text style={styles.title}>
        {item.order_item}
      </Text>
      <Text style={styles.sub}>Order status: {item.order_status}</Text>
      <Text style={styles.sub}>Last message: {item.last_time || '—'}</Text>
    </TouchableOpacity>
  );

  return (
    <View style={styles.container}>
      <FlatList
        data={rooms}
        keyExtractor={(i) => i.room_id.toString()}
        renderItem={({ item }) => <Item item={item} />}
        ListEmptyComponent={
          <Text style={{ textAlign: 'center', marginTop: 40 }}>
            No chats yet
          </Text>
        }
      />
    </View>
  );
};

export default ChatListScreen;

const styles = StyleSheet.create({
  container:{ flex:1, padding:16, backgroundColor:'#fff' },
  card:{ padding:12, borderWidth:1, borderColor:'#ccc', borderRadius:8, marginBottom:10 },
  title:{ fontSize:16, fontWeight:'bold', marginBottom:4 },
  sub:{ fontSize:14, color:'#555' },
});
