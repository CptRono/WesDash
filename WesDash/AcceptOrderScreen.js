// screen/AcceptOrderScreen.js
import React, { useEffect, useState, useCallback } from 'react';
import {
  View,
  Text,
  Button,
  StyleSheet,
  Alert,
  FlatList,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useFocusEffect } from '@react-navigation/native';
import { BASE_URL } from '../config';

const AcceptOrderScreen = ({ route, navigation }) => {
  const { username = 'Unknown', role = 'user' } = route.params ?? {};

  const [orders,     setOrders]  = useState([]);
  const [sessionID,  setSID]     = useState(null);

  /* ---------- pull orders ---------- */
  const fetchOrders = async () => {
    try {
      const resp = await fetch(`${BASE_URL}/WesDashAPI/accept_order.php`, {
        method: 'GET',
        credentials: 'include',
        headers: { 'Content-Type':'application/json', Cookie:`PHPSESSID=${sessionID}` },
      });
      const data = await resp.json();
      data.success
        ? setOrders(data.orders)
        : Alert.alert('Error', data.message || 'Failed to fetch orders.');
    } catch {
      Alert.alert('Error', 'Network error while fetching orders.');
    }
  };

  /* ---------- first load ---------- */
  useEffect(() => {
    (async () => {
      const id = await AsyncStorage.getItem('PHPSESSID');
      if (!id) { Alert.alert('Error','Session ID not found.'); return; }
      setSID(id);
      fetchOrders();
    })();
  }, []);

  /* ---------- refresh on focus ---------- */
  useFocusEffect(useCallback(() => {
    if (sessionID) fetchOrders();
  }, [sessionID]));

  /* ---------- accept ---------- */
  const handleAcceptOrder = async (id) => {
    try {
      const resp = await fetch(`${BASE_URL}/WesDashAPI/accept_order.php`, {
        method:'PUT',
        credentials:'include',
        headers:{ 'Content-Type':'application/json' },
        body: JSON.stringify({ id }),
      });
      const data = await resp.json();
      if (data.success) {
        setOrders(prev => prev.map(o =>
          o.id===id ? { ...o, status:'accepted', room_id:data.room_id||o.room_id } : o));
        data.room_id
          ? navigation.navigate('Chat',{ roomId:data.room_id, username })
          : Alert.alert('Success','Order accepted successfully!');
      } else Alert.alert('Error', data.message || 'Failed to accept order.');
    } catch { Alert.alert('Error','Failed to accept order. Please try again.'); }
  };

  /* ---------- drop-off ---------- */
  const handleDropOffOrder = async (id) => {
    try {
      const resp = await fetch(`${BASE_URL}/WesDashAPI/accept_order.php`, {
        method:'PUT',
        credentials:'include',
        headers:{ 'Content-Type':'application/json' },
        body: JSON.stringify({ id, action:'drop_off' }),
      });
      const data = await resp.json();
      if (data.success) {
        Alert.alert('Success','Order dropped off successfully!');
        fetchOrders();
      } else Alert.alert('Error', data.message || 'Failed to drop off order.');
    } catch { Alert.alert('Error','Failed to drop off order. Please try again.'); }
  };

  /* ---------- 坐标合法性工具 ---------- */
  const hasValidCoords = (loc) => {
    if (!loc) return false;                 
    const p = String(loc).split(',').map(s => parseFloat(s.trim()));
    return p.length === 2 && p.every(n => Number.isFinite(n));
  };

  /* ---------- 单条卡片 ---------- */
  const OrderItem = ({ item }) => (
    <View style={styles.card}>
      <Text style={styles.label}>Item:</Text>
      <Text style={styles.text}>{item.item}</Text>

      <Text style={styles.label}>Quantity:</Text>
      <Text style={styles.text}>{item.quantity}</Text>

      <Text style={styles.label}>Drop-off:</Text>
      <Text style={styles.text}>{item.drop_off_location}</Text>

      <Text style={styles.label}>Speed:</Text>
      <Text style={styles.text}>{item.delivery_speed}</Text>

      <Text style={styles.label}>Status:</Text>
      <View style={[
        styles.statusBox,
        item.status==='pending'  ? styles.pending  :
        item.status==='accepted' ? styles.accepted : styles.completed]}>
        <Text style={styles.statusTxt}>{item.status.toUpperCase()}</Text>
      </View>

      {item.status==='pending' && (
        <Button title="ACCEPT" onPress={()=>handleAcceptOrder(item.id)}/>
      )}

      {item.status==='accepted' && (
        <>
          {/* 导航到店铺（如果有） */}
          {hasValidCoords(item.purchase_mode) && (
            <Button
              title="NAVIGATE TO STORE"
              onPress={()=>navigation.navigate('NavigationToLocationScreen',
                       { dropOffLocation:item.purchase_mode })}/>
          )}

          {/* 导航到收货点 */}
          {hasValidCoords(item.drop_off_location) ? (
            <Button
              title="NAVIGATE TO DROP-OFF"
              onPress={()=>navigation.navigate('NavigationToLocationScreen',
                       { dropOffLocation:item.drop_off_location })}/>
          ) : (
            <Button title="NO DROP-OFF MAP" color="#999"
              onPress={()=>Alert.alert('No coordinates','This order has no drop-off location.')}/>
          )}

          {/* 聊天 */}
          {item.room_id && (
            <Button title="CHAT" color="#007bff"
              onPress={()=>navigation.navigate('Chat',{ roomId:item.room_id, username })}/>
          )}

          {/* 完成配送 */}
          <Button title="DROP OFF" onPress={()=>handleDropOffOrder(item.id)}/>
        </>
      )}
    </View>
  );

  /* ---------- render ---------- */
  return (
    <View style={styles.container}>
      <View style={styles.infoBox}>
        <Text style={styles.infoTxt}>Logged in as: {username}</Text>
        <Text style={styles.infoTxt}>Role: {role==='dasher' ? 'Dasher' : 'User'}</Text>
      </View>

      <Text style={styles.heading}>Orders for Acceptance</Text>

      <FlatList
        data={orders}
        keyExtractor={i=>i.id.toString()}
        renderItem={({item})=><OrderItem item={item}/>}
        ListEmptyComponent={<Text style={{textAlign:'center', marginTop:40}}>No orders available.</Text>}
      />
    </View>
  );
};

/* ---------- styles ---------- */
const styles = StyleSheet.create({
  container:{ flex:1, padding:16, backgroundColor:'#fff' },
  heading:  { fontSize:24, fontWeight:'bold', marginBottom:10, textAlign:'center' },
  infoBox:  { paddingVertical:8, borderBottomWidth:1, borderColor:'#eee', marginBottom:12 },
  infoTxt:  { fontSize:16, marginBottom:4, fontWeight:'500', color:'#333' },

  card:{ padding:12, borderWidth:1, borderColor:'#ccc', borderRadius:6, marginBottom:14 },
  label:{ fontSize:16, fontWeight:'bold', marginTop:4 },
  text:{ fontSize:16, marginBottom:4 },

  statusBox:{ padding:6, borderRadius:4, alignItems:'center', marginBottom:8 },
  pending:{ backgroundColor:'#ffcc00' },
  accepted:{ backgroundColor:'#66cc66' },
  completed:{ backgroundColor:'#007bff' },
  statusTxt:{ color:'#fff', fontWeight:'bold' },
});

export default AcceptOrderScreen;
